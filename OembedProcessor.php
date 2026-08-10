<?php

namespace YmEmbed;

use ProcessWire\WireHttp;
use ProcessWire\WireData;

class OembedProcessor extends WireData
{
    public function ___getProviders(): array
    {
        return [
            'youtube' => [
                'pattern'   => '~youtube\.com/watch|youtu\.be/~i',
                'endpoint'  => 'https://www.youtube.com/oembed',
                'normalize' => [$this, 'normalizeYoutube'],
            ],
            'vimeo' => [
                'pattern'   => '~vimeo\.com/~i',
                'endpoint'  => 'https://vimeo.com/api/oembed.json',
                'normalize' => [$this, 'normalizeVimeo'],
            ],
            'sketchfab' => [
                'pattern'   => '~sketchfab\.com/(3d-models|models)/|skfb\.ly/~i',
                'endpoint'  => 'https://sketchfab.com/oembed',
                'normalize' => [$this, 'normalizeSketchfab'],
                'attribution_required' => true
            ],
        ];
    }

    public function ___detectProvider(string $url): ?array
    {
        foreach ($this->getProviders() as $name => $provider) {
            if (preg_match($provider['pattern'], $url)) {
                $provider['name'] = $name;
                return $provider;
            }
        }
        return null;
    }

    public function ___fetch(string $url): ?array
    {
        $provider = $this->detectProvider($url);
        if (!$provider) return null;

        $http = new WireHttp();
        $endpoint = $provider['endpoint'] . '?url=' . urlencode($url);

        $json = $http->get($endpoint);
        if (!$json) return null;

        $data = json_decode($json, true);
        if (!is_array($data)) return null;

        if (!isset($data['url'])) {
            $data['url'] = $url;
        }

        return $this->normalizeData($provider, $data);
    }

    public function ___normalizeData(array $provider, array $data): array
    {
        if (isset($provider['normalize']) && is_callable($provider['normalize'])) {
            return call_user_func($provider['normalize'], $data, $provider);
        }

        bd($data);

        // Opaque/generic fallback (e.g. sketchfab): keep provider's own html blob as-is.
        // Not decomposed into url/data-src — see Embed::renderPlaceholder() opaque branch.
        return [
            'provider'      => $provider['name'] ?? 'generic',
            'url'           => $data['url'] ?? '',
            'title'         => $data['title'] ?? '',
            'thumbnail_url' => $data['thumbnail_url'] ?? '',
            'width'         => $data['width'] ?? null,
            'height'        => $data['height'] ?? null,
            'aspect_ratio'  => (isset($data['width'], $data['height']) && $data['height'] > 0)
                ? round($data['width'] / $data['height'], 6)
                : 1.777778,
            'html'          => $data['html'] ?? '',
        ];
    }

    /**
     * Builds the storable iframe markup via Embed::renderIframe() — eager (real src),
     * {title} left as a literal placeholder for Embed::render() to resolve at display time.
     * Requires $newData['url'] to already be a clean iframe-embeddable URL, not a page URL.
     */
    protected function buildEmbedHtml(array $newData): string
    {
        if (empty($newData['url'])) {
            return '';
        }

        $embed = new Embed([
            'provider'     => $newData['provider'],
            'url'          => $newData['url'],
            'aspect_ratio' => $newData['aspect_ratio'] ?? 1.777778,
        ]);

        return $embed->renderIframe();
    }

    public function normalizeYoutube(array $data, $provider): array
    {
        $newData = [];
        $newData['provider'] = 'youtube';
        $newData['src_url']  = $data['src_url'] ?? '';
        $newData['title']    = $data['title'] ?? '';

        if (!empty($data['thumbnail_url'])) {
            $thumbMaxRes = preg_replace('~/hqdefault\.jpg$~', '/maxresdefault.jpg', $data['thumbnail_url']);
            $http = new WireHttp();
            $http->head($thumbMaxRes);

            $newData['thumbnail_url'] = ($http->getHttpCode() === 200)
                ? $thumbMaxRes
                : $data['thumbnail_url'];
        } else {
            $newData['thumbnail_url'] = '';
        }

        if (isset($data['width'], $data['height']) && $data['height'] > 0) {
            $newData['width']        = $data['width'];
            $newData['height']       = $data['height'];
            $newData['aspect_ratio'] = round($data['width'] / $data['height'], 6);
        } else {
            $newData['aspect_ratio'] = 1.777778;
        }

        $ogHtml = $data['html'] ?? '';
        $newData['url'] = '';

        if (preg_match('~src="([^"]+)"~', $ogHtml, $srcMatch)) {
            $embedUrl = str_replace('youtube.com/', 'youtube-nocookie.com/', $srcMatch[1]);
            $newData['url'] = $embedUrl;

            if (empty($newData['title']) && preg_match('~/(?:embed/|v/|watch\?v=|youtu\.be/)([\w-]{11})~i', $srcMatch[1], $idMatch)) {
                $newData['src_url'] = 'https://www.youtube.com/watch?v=' . $idMatch[1];
            }

            if (empty($newData['title']) && preg_match('~title="([^"]*)"~', $ogHtml, $titleMatch)) {
                $newData['title'] = $titleMatch[1];
            }
        }

        $newData['html'] = $this->buildEmbedHtml($newData);

        return $newData;
    }

    public function normalizeVimeo(array $data): array
    {
        $newData = [];
        $newData['provider']      = 'vimeo';
        $newData['title']         = $data['title'] ?? '';
        $newData['thumbnail_url'] = $data['thumbnail_url'] ?? '';
        // Vimeo's oembed 'url' is the canonical page, not an embed src — keep it as src_url,
        // matching youtube's url/src_url convention (fixed from the old pass-through).
        $newData['src_url']       = $data['url'] ?? '';

        if (isset($data['width'], $data['height']) && $data['height'] > 0) {
            $newData['width']        = $data['width'];
            $newData['height']       = $data['height'];
            $newData['aspect_ratio'] = round($data['width'] / $data['height'], 6);
        } else {
            $newData['aspect_ratio'] = 1.777778;
        }

        $ogHtml = $data['html'] ?? '';
        $newData['url'] = '';

        if (preg_match('~src="([^"]+)"~', $ogHtml, $srcMatch)) {
            $newData['url'] = $srcMatch[1];
        }

        $newData['html'] = $this->buildEmbedHtml($newData);

        return $newData;
    }

    public function normalizeSketchfab(array $data, array $provider): array
    {

        $newData = [];


        if (isset($provider['attribution_required']) && $provider['attribution_required']){
            $newData['attribution_required'] = 1;
        }

        $newData['provider']      = 'sketchfab';
        $newData['title']         = $data['title'] ?? '';
        $newData['thumbnail_url'] = $data['thumbnail_url'] ?? '';
        $newData['src_url']       = $data['url'] ?? ''; // oembed 'url' = canonical model page

        if (isset($data['width'], $data['height']) && $data['height'] > 0) {
            $newData['width']        = $data['width'];
            $newData['height']       = $data['height'];
            $newData['aspect_ratio'] = round($data['width'] / $data['height'], 6);
        } else {
            $newData['aspect_ratio'] = 1.777778;
        }

        $newData['url'] = '';
        if (preg_match('~src="([^"]+)"~', $data['html'] ?? '', $srcMatch)) {
            $newData['url'] = $srcMatch[1];
        }

        $newData['description'] = $this->buildAttributionHtml($data);
        $newData['html']        = $this->buildEmbedHtml($newData);

        return $newData;
    }

    /**
     * Built once, from oembed response fields directly (title/url/author_name/author_url/
     * provider_name/provider_url) — no scraping needed, sketchfab returns all of it as data.
     * Stored into description at fetch time; never rebuilt at render.
     */
    protected function buildAttributionHtml(array $data): string
    {
        $title       = htmlspecialchars($data['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $modelUrl    = htmlspecialchars($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
        $author      = htmlspecialchars($data['author_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $authorUrl   = htmlspecialchars($data['author_url'] ?? '', ENT_QUOTES, 'UTF-8');
        $providerNm  = htmlspecialchars($data['provider_name'] ?? 'Sketchfab', ENT_QUOTES, 'UTF-8');
        $providerUrl = htmlspecialchars($data['provider_url'] ?? 'https://sketchfab.com', ENT_QUOTES, 'UTF-8');

        $titleLink = $modelUrl
            ? sprintf('<a href="%s" target="_blank" rel="nofollow">%s</a>', $modelUrl, $title)
            : $title;

        $authorPart = $author
            ? sprintf(' by %s', $authorUrl ? sprintf('<a href="%s" target="_blank" rel="nofollow">%s</a>', $authorUrl, $author) : $author)
            : '';

        $providerLink = sprintf('<a href="%s" target="_blank" rel="nofollow">%s</a>', $providerUrl, $providerNm);

        return sprintf('%s%s on %s', $titleLink, $authorPart, $providerLink);
    }
}
