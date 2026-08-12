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
                'params'    => ['maxwidth' => 1280], // Vimeo automatically delivers scaled thumbnail_url
                'normalize' => [$this, 'normalizeVimeo'],
            ],
            'sketchfab' => [
                'pattern'   => '~sketchfab\.com/(3d-models|models)/|skfb\.ly/~i',
                'endpoint'  => 'https://sketchfab.com/oembed',
                'normalize' => [$this, 'normalizeSketchfab'],
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

        $queryParams = array_merge(
            ['url' => $url],
            $provider['params'] ?? []
        );

        $http = new WireHttp();
        $endpoint = $provider['endpoint'] . '?' . http_build_query($queryParams);

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
            $result = call_user_func($provider['normalize'], $data, $provider);
            if (!array_key_exists('attribution_required', $result)) {
                $result['attribution_required'] = isset($provider['attribution_required']) ? (int) $provider['attribution_required'] : 0;
            }
            return $result;
        }

        return [
            'provider'             => $provider['name'] ?? 'generic',
            'url'                  => $data['url'] ?? '',
            'title'                => $data['title'] ?? '',
            'thumbnail_url'        => $data['thumbnail_url'] ?? '',
            'width'                => $data['width'] ?? null,
            'height'               => $data['height'] ?? null,
            'aspect_ratio'         => (isset($data['width'], $data['height']) && $data['height'] > 0)
                ? round($data['width'] / $data['height'], 6)
                : 1.777778,
            'html'                 => $data['html'] ?? '',
            'attribution_required' => !empty($provider['attribution_required']) ? 1 : 0,
        ];
    }

    protected function buildEmbedHtml(array $newData): string
    {
        if (empty($newData['url'])) {
            return '';
        }

        $embed = new Embed();
        $embed->setArray($newData);

        return $embed->render();
    }

    public function normalizeYoutube(array $data, array $provider = []): array
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

    public function normalizeVimeo(array $data, array $provider = []): array
    {
        $newData = [];
        $newData['provider']      = 'vimeo';
        $newData['title']         = $data['title'] ?? '';
        $newData['thumbnail_url'] = $data['thumbnail_url_with_play_button'] ?? '';
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

        if (!empty($newData['url'])) {
            $newData['url'] = str_replace('?app_id=', '?dnt=1&app_id=', $newData['url']);
        }

        $newData['html'] = $this->buildEmbedHtml($newData);

        return $newData;
    }

    public function normalizeSketchfab(array $data, array $provider = []): array
    {
        $newData = [];
        $newData['provider']      = 'sketchfab';
        $newData['title']         = $data['title'] ?? '';
        $newData['thumbnail_url'] = $data['thumbnail_url'] ?? '';
        $newData['src_url']       = $data['url'] ?? '';

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