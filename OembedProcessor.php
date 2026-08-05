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

        return [
            'provider'     => $provider['name'] ?? 'generic',
            'url'          => $data['url'] ?? '',
            'title'        => $data['title'] ?? '',
            'thumbnail_url'=> $data['thumbnail_url'] ?? '',
            'width'        => $data['width'] ?? null,
            'height'       => $data['height'] ?? null,
            'aspect_ratio' => (isset($data['width'], $data['height']) && $data['height'] > 0)
                ? round($data['width'] / $data['height'], 6)
                : 1.777778,
            'html'         => $data['html'] ?? '',
        ];
    }

    public function normalizeYoutube(array $data): array
    {
        $newData = [];
        $newData['provider'] = 'youtube';
        $newData['url']      = $data['url'] ?? '';
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

        if (preg_match('~src="([^"]+)"~', $ogHtml, $srcMatch)) {
            $src = str_replace('youtube.com/', 'youtube-nocookie.com/', $srcMatch[1]);

            if (empty($newData['title']) && preg_match('~title="([^"]*)"~', $ogHtml, $titleMatch)) {
                $newData['title'] = $titleMatch[1];
            }

            $newData['html'] = sprintf(
                '<div class="embed embed--%s" style="aspect-ratio: %s;"><iframe class="embed__iframe" src="%s" title="{title}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>',
                $newData['provider'],
                $newData['aspect_ratio'],
                htmlspecialchars($src, ENT_QUOTES)
            );
        } else {
            $newData['html'] = '';
        }

        return $newData;
    }

    public function normalizeVimeo(array $data): array
    {
        return [
            'provider'      => 'vimeo',
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
}