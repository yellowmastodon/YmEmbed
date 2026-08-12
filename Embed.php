<?php

namespace YmEmbed;

use ProcessWire\WireData;
use ProcessWire\Language;

/**
 * @property string $provider
 * @property string $url
 * @property string $src_url
 * @property string $html
 * @property string $title
 * @property string $thumbnail_url
 * @property ?float $aspect_ratio
 * @property ?int $width
 * @property ?int $height
 * @property string $description
 * @property int $showtitle
 * @property int $attribution_required
 */
class Embed extends WireData
{
    public const SCHEMA = [
        'provider'      => ['db' => 'provider',      'type' => 'text',     'schema' => 'VARCHAR(50) NOT NULL DEFAULT ""', 'maxLength' => 50],
        'url'           => ['db' => 'url',           'type' => 'url',      'schema' => 'TEXT'],
        'src_url'       => ['db' => 'src_url',       'type' => 'url',      'schema' => 'TEXT'],
        'html'          => ['db' => 'data',          'type' => 'html',     'schema' => 'MEDIUMTEXT'],
        'title'         => ['db' => 'title',         'type' => 'text',     'schema' => 'TEXT', 'multilang' => true],
        'thumbnail_url' => ['db' => 'thumbnail_url', 'type' => 'url',      'schema' => 'TEXT'],
        'aspect_ratio'  => ['db' => 'aspect_ratio',  'type' => 'float',    'schema' => 'DECIMAL(8,6) NOT NULL DEFAULT 1.777778'],
        'showtitle'     => ['db' => 'showtitle',     'type' => 'int',      'schema' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1'],
        'description'   => ['db' => 'description',   'type' => 'html',     'schema' => 'TEXT', 'multilang' => true],
        'width'         => ['db' => null,            'type' => 'int',      'schema' => null],
        'height'        => ['db' => null,            'type' => 'int',      'schema' => null],
        'attribution_required' => ['db' => 'attribution_required', 'type' => 'int', 'schema' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0']
    ];

    public function __construct(array $data = [])
    {
        $this->set('provider', '');
        $this->set('url', '');
        $this->set('src_url', '');
        $this->set('html', '');
        $this->set('title', '');
        $this->set('thumbnail_url', '');
        $this->set('aspect_ratio', 1.777778);
        $this->set('showtitle', FieldtypeEmbed::titleHidden);
        $this->set('description', '');
        $this->set('attribution_required', 0);

        if (!empty($data)) {
            $this->setArray($data);
        }
    }

    public function get($key, $language = null)
    {
        if (!self::isMultilangProperty($key) || preg_match('/\d+$/', $key)) {
            return parent::get($key);
        }
        return $this->getLanguageValue($key, $language);
    }

    public function set($key, $value, $language = null)
    {
        if ($language !== null && self::isMultilangProperty($key)) {
            return $this->setLanguageValue($key, $value, $language);
        }
        if ($this->isAllowedProperty($key)) {
            return parent::set($key, $value);
        }
        return $this;
    }

    public function getLanguageValue(string $key, $language = null)
    {
        $languages = $this->wire('languages');
        if (!$languages) {
            return parent::get($key);
        }
        $langObj = $this->resolveLanguage($language);
        if (!$langObj || $langObj->isDefault()) {
            return parent::get($key);
        }
        $langKey = $key . $langObj->id;
        $value   = parent::get($langKey);
        if ($value === null || $value === '') {
            $value = parent::get($key);
        }
        return $value;
    }

    public function setLanguageValue(string $key, $value, $language = null)
    {
        $langObj = $this->resolveLanguage($language);
        if ($langObj && !$langObj->isDefault()) {
            $key = $key . $langObj->id;
        }
        if ($this->isAllowedProperty($key)) {
            parent::set($key, $value);
        }
        return $this;
    }

    protected function resolveLanguage($language = null)
    {
        $languages = $this->wire('languages');
        if (!$languages) return null;

        if ($language === null) {
            $user = $this->wire('user');
            return ($user && $user->language) ? $user->language : $languages->getDefault();
        }
        if ($language instanceof Language || $language instanceof \ProcessWire\Page) {
            return $language;
        }
        if (is_numeric($language) || is_string($language)) {
            return $languages->get($language);
        }
        return $languages->getDefault();
    }

    public function isAllowedProperty(string $key): bool
    {
        if (array_key_exists($key, self::SCHEMA)) {
            return true;
        }
        foreach (self::SCHEMA as $prop => $meta) {
            if (!empty($meta['multilang'])) {
                if (preg_match('/^' . preg_quote($prop, '/') . '\d+$/', $key)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function isMultilangProperty(string $key): bool
    {
        return !empty(self::SCHEMA[$key]['multilang']);
    }

    public static function allowedProperties(): array
    {
        return array_keys(self::SCHEMA);
    }

    /**
     * Per-provider DISPLAY metadata only (button label, iframe permissions/attrs/params).
     * attribution_required is NOT sourced here — it's a stored, persisted field (see
     * SCHEMA), set once by OembedProcessor at fetch time.
     */
    public function ___providerMeta(): array
    {
        return [
            'youtube' => [
                'label'           => 'Play YouTube video: %s',
                'consent'         => 'youtube',
                'buttonInnerHTML' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 67 60" fill="" focusable="false" aria-hidden="true" style="pointer-events: none; display: inherit; width: 100%; height: 100%;"><path d="M63 14.87a7.885 7.885 0 00-5.56-5.56C52.54 8 32.88 8 32.88 8S13.23 8 8.32 9.31c-2.7.72-4.83 2.85-5.56 5.56C1.45 19.77 1.45 30 1.45 30s0 10.23 1.31 15.13c.72 2.7 2.85 4.83 5.56 5.56C13.23 52 32.88 52 32.88 52s19.66 0 24.56-1.31c2.7-.72 4.83-2.85 5.56-5.56C64.31 40.23 64.31 30 64.31 30s0-10.23-1.31-15.13z"></path><path fill="#FFF" class="logo-arrow" d="M26.6 39.43L42.93 30 26.6 20.57z"></path></svg>',
                'iframe'          => [
                    'allow'  => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                    'params' => ['autoplay' => '1'],
                ],
            ],
            'vimeo' => [
                'label'           => 'Play Vimeo video: %s',
                'consent'         => 'vimeo',
                'buttonInnerHTML' => '<svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" ><path d="M19 12C19 12.3557 18.8111 12.6846 18.5039 12.8638L6.50387 19.8638C6.19458 20.0442 5.81243 20.0455 5.50194 19.8671C5.19145 19.6888 5 19.3581 5 19L5 5C5 4.64193 5.19145 4.3112 5.50194 4.13286C5.81243 3.95452 6.19458 3.9558 6.50387 4.13622L18.5039 11.1362C18.8111 11.3154 19 11.6443 19 12Z"></path></svg>',
                'iframe'          => [
                    'allow'  => 'autoplay; fullscreen; picture-in-picture; clipboard-write',
                    'params' => ['autoplay' => '1'],
                ],
            ],
            'sketchfab' => [
                'label'           => 'Load 3D model: %s',
                'consent'         => 'sketchfab',
                'buttonInnerHTML' => '<svg viewBox="0 0 220 220" xmlns="http://www.w3.org/2000/svg"><path d="m19.5 22.5c0-4.3 2.9-6 6.6-3.9l174.6 96.4c4.1 2.3 4.1 6.2 0 8.5l-174.6 95.9c-3.7 2.1-6.6 0.4-6.6-3.9z" fill="#fff"/></svg><span class="hover-text">3D</span>',
                'iframe'          => [
                    'allow'  => 'autoplay; fullscreen; xr-spatial-tracking',
                    'params' => ['autostart' => '1'],
                    'attrs'  => [
                        'xr-spatial-tracking'              => true,
                        'execution-while-out-of-viewport'  => true,
                        'execution-while-not-rendered'     => true,
                    ],
                ],
            ],
        ];
    }

    public function requiresAttribution(): bool
    {
        return (bool) $this->attribution_required;
    }

    protected function resolveTitleAttr(bool $titlePlaceholder): string
    {
        if ($titlePlaceholder) {
            return '{title}';
        }
        $title = (string) $this->getLanguageValue('title');
        return htmlspecialchars($title ?: 'Embedded content', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Merges provider params (autoplay, autostart, etc.) into $url's query string,
     * preserving any params already present. Was missing entirely from this file —
     * buildIframeTag() called it without a definition, causing a fatal error on
     * every url-based provider render.
     */
    protected function appendUrlParams(string $url, array $params): string
    {
        if (empty($url) || empty($params)) {
            return $url;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $existingParams);
        $mergedParams = array_merge($existingParams, $params);

        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host     = $parts['host'] ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path'] ?? '';
        $query    = !empty($mergedParams) ? '?' . http_build_query($mergedParams) : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "$scheme$host$port$path$query$fragment";
    }

    public function ___buildIframeTag(string $titleAttr): string
    {
        $meta   = $this->providerMeta()[$this->provider] ?? [];
        $iframe = $meta['iframe'] ?? [];
        $allow  = $iframe['allow'] ?? 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';

        $src = $this->appendUrlParams($this->url, $iframe['params'] ?? []);

        $extra = '';
        foreach (($iframe['attrs'] ?? []) as $attrName => $val) {
            if ($val === true) {
                $extra .= ' ' . htmlspecialchars($attrName, ENT_QUOTES, 'UTF-8');
            } elseif ($val !== false && $val !== null) {
                $extra .= sprintf(' %s="%s"', htmlspecialchars($attrName, ENT_QUOTES, 'UTF-8'), htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'));
            }
        }

        return sprintf(
            '<iframe class="embed__iframe" data-src="%s" title="%s" loading="lazy" allow="%s"%s allowfullscreen></iframe>',
            htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
            $titleAttr,
            htmlspecialchars($allow, ENT_QUOTES, 'UTF-8'),
            $extra
        );
    }

    /**
     * Single markup path — placeholder + consent overlay + iframe(data-src). No
     * eager output; opaque providers (no $this->url) fall back to the stored html
     * blob as-is, since it can't be safely decomposed into this shape.
     */
    public function ___renderPlaceholder(array $options = []): string
    {
        if (empty($this->url)) {
            return $this->html;
        }

        $options   = array_merge(['title_placeholder' => true], $options);
        $titleAttr = $this->resolveTitleAttr($options['title_placeholder']);
        $iframe    = $this->buildIframeTag($titleAttr);
        $placeholder_img = sprintf('<img class="embed__placeholder-img" src=%s alt="" loading="lazy">', htmlspecialchars($this->thumbnail_url, ENT_QUOTES, 'UTF-8'));

        $meta    = $this->providerMeta()[$this->provider] ?? ['label' => 'Play', 'consent' => $this->provider];

        $label = $meta['label'] ?? 'Play: %s';
        $label = sprintf($meta['label'], $this->title);
        $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        //$label   = htmlspecialchars($meta['label'] ?? 'Play: %s', ENT_QUOTES, 'UTF-8');
        $consent = htmlspecialchars($meta['consent'] ?? $this->provider, ENT_QUOTES, 'UTF-8');
        $name    = htmlspecialchars(ucfirst($this->provider), ENT_QUOTES, 'UTF-8');
        $inner   = $meta['buttonInnerHTML'] ?? '<span class="embed__button-text">Play</span>';

        $button = sprintf(
            '<button type="button" class="embed__button embed__button--play" aria-label="%s">%s</button>',
            $label,
            $inner
        );

        $placeholder = sprintf('<div class="embed__placeholder">%s</div>', $button);

        $consentBlock = sprintf(
            '<div class="embed__consent"><button type="button" class="embed__button embed__button--consent" data-consent="%s" aria-label="Enable %s content"></button><p class="embed__consent-text">Enable content from %s.</p></div>',
            $consent,
            $name,
            $name
        );

        return $placeholder . $placeholder_img . $consentBlock . $iframe ;
    }

    /**
     * Options:
     *   consent (bool|string) default false — outputs data-consent="{category}" on
     *                         the outer element when enabled. Client-side JS reads
     *                         this to decide whether to gate the placeholder behind
     *                         a consent prompt.
     */
    public function ___render(array $options = []): string
    {
        $options = array_merge([
            'consent' => false,
        ], $options);

        $class = isset($options['class']) ? ' ' . (string) htmlspecialchars($options['class']) : '';

        $body = $this->renderPlaceholder($options);
        if (empty($body)) {
            return '';
        }

        $safeTitle = htmlspecialchars((string) $this->getLanguageValue('title') ?: 'Embedded content', ENT_QUOTES, 'UTF-8');
        $body = str_replace('{title}', $safeTitle, $body);

        $title               = (string) $this->getLanguageValue('title');
        $description         = (string) $this->getLanguageValue('description');
        $showTitleMode       = (int) $this->showtitle;
        $requiresAttribution = $this->requiresAttribution();

        $hasCaption = $requiresAttribution
            || ($showTitleMode === FieldtypeEmbed::titleSeparate && !empty($description))
            || ($showTitleMode === FieldtypeEmbed::titleShow && !empty($title));

        $tag = $hasCaption ? 'figure' : 'div';

        $attributes = ['data-state="placeholder"'];
        if (!empty($options['consent'])) {
            $consentCategory = is_string($options['consent'])
                ? $options['consent']
                : htmlspecialchars($this->provider, ENT_QUOTES, 'UTF-8');
            $attributes[] = sprintf('data-consent="%s"', $consentCategory);
        }
        $attrString = ' ' . implode(' ', $attributes);

        $open = sprintf(
            '<%s class="embed embed--%s%s"%s><div class="embed__wrapper" style="aspect-ratio:%s">',
            $tag,
            $class,
            htmlspecialchars($this->provider, ENT_QUOTES, 'UTF-8'),
            $attrString,
            $this->aspect_ratio
        );
        
        $caption = '';
        if ($hasCaption) {
            if ($requiresAttribution) {
                $caption = sprintf('<figcaption class="embed__caption embed__caption--attribution">%s</figcaption>', $description);
            } elseif ($showTitleMode === FieldtypeEmbed::titleSeparate) {
                $caption = sprintf('<figcaption class="embed__caption">%s</figcaption>', $description);
            } else {
                $caption = sprintf('<figcaption class="embed__caption">%s</figcaption>', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'));
            }
        }

        return $open . $body . '</div>' . $caption . "</{$tag}>";
    }

    public static function assets(string $type = ''): string
    {
        static $html = null;

        if ($html === null) {
            $config = \ProcessWire\wire('config');
            $url    = $config->urls->FieldtypeEmbed;
            $path   = $config->paths->FieldtypeEmbed;

            $cssUrl = $url . 'build/lazyframe.css?mod=' . filemtime($path . 'build/lazyframe.css');
            $jsUrl  = $url . 'build/lazyframe.js?mod=' . filemtime($path . 'build/lazyframe.js');

            $html = [
                'css' => sprintf('<link rel="stylesheet" href="%s">', $cssUrl),
                'js'  => sprintf('<script src="%s" defer></script>', $jsUrl),
            ];
        }

        if ($type === 'css') {
            return $html['css'];
        }

        if ($type === 'js') {
            return $html['js'];
        }

        return $html['css'] . "\n" . $html['js'];
    }
}