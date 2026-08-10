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
        'attribution_required' => ['db' => 'attribution_required', 'type' => 'int', 'schema' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1']
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
     * Per-provider display metadata (not fetch config — that stays in OembedProcessor).
     * Instance hookable rather than static: avoids ProcessWire's static-hook calling
     * quirks, and an Embed instance is already at hand everywhere this is used.
     */
    public function ___providerMeta(): array
    {
        return [
            'youtube'   => ['label' => 'Play video',    'consent' => 'youtube'],
            'vimeo'     => ['label' => 'Play video',    'consent' => 'vimeo'],
            'sketchfab' => ['label' => 'Load 3D model', 'consent' => 'sketchfab'],
        ];
    }

    /**
     * .embed__wrapper carries the thumbnail background and aspect-ratio box; the iframe
     * sits on top of it (hidden via CSS in the 'locked'/'unlocked' data-state until played).
     */
    protected function wrapperOpenTag(): string
    {
        $bg = $this->thumbnail_url
            ? sprintf(';background-image:url(%s)', htmlspecialchars($this->thumbnail_url, ENT_QUOTES, 'UTF-8'))
            : '';

        return sprintf(
            '<div class="embed__wrapper" style="aspect-ratio:%s%s">',
            $this->aspect_ratio,
            $bg
        );
    }

    protected function resolveTitleAttr(bool $titlePlaceholder): string
    {
        if ($titlePlaceholder) {
            return '{title}';
        }
        $title = (string) $this->getLanguageValue('title');
        return htmlspecialchars($title ?: 'Embedded content', ENT_QUOTES, 'UTF-8');
    }

    protected function buildIframeTag(string $srcAttr, string $titleAttr): string
    {
        return sprintf(
            '<iframe class="embed__iframe" %s="%s" title="%s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>',
            $srcAttr,
            htmlspecialchars($this->url, ENT_QUOTES, 'UTF-8'),
            $titleAttr
        );
    }

    /**
     * Returns just .embed__wrapper > iframe — no outer .embed element, no data-state.
     * This is what gets stored as the DB fallback blob (via OembedProcessor) and also
     * what's returned in non-lazy render() calls before the outer tag is added.
     *
     * Options:
     *   lazyframe (bool)          default false — emits data-src instead of src
     *   title_placeholder (bool)  default true  — emits literal {title} instead of resolving it
     *                             (true is what gets stored to DB via OembedProcessor)
     *
     * Falls back to the stored html blob for opaque providers (no clean iframe url,
     * e.g. sketchfab / generic oembed fallback).
     */
    public function ___renderIframe(array $options = []): string
    {
        if (empty($this->url)) {
            return $this->html;
        }

        $options = array_merge([
            'lazyframe'         => false,
            'title_placeholder' => true,
        ], $options);

        $srcAttr   = $options['lazyframe'] ? 'data-src' : 'src';
        $titleAttr = $this->resolveTitleAttr($options['title_placeholder']);

        return $this->wrapperOpenTag() . $this->buildIframeTag($srcAttr, $titleAttr) . '</div>';
    }

    /**
     * Returns wrapper+iframe(data-src) plus placeholder/consent overlays — no outer
     * .embed element (render() adds that, with data-state="locked").
     *
     * Opaque providers (empty $this->url) can't be safely split into placeholder + lazy
     * iframe from a stored html blob, so they fall back to eager renderIframe().
     */
    public function ___renderPlaceholder(array $options = []): string
    {
        if (empty($this->url)) {
            return $this->renderIframe($options);
        }

        $options   = array_merge(['title_placeholder' => true], $options);
        $titleAttr = $this->resolveTitleAttr($options['title_placeholder']);
        $iframe    = $this->wrapperOpenTag() . $this->buildIframeTag('data-src', $titleAttr) . '</div>';

        $meta    = $this->providerMeta()[$this->provider] ?? ['label' => 'Play', 'consent' => $this->provider];
        $label   = htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8');
        $consent = htmlspecialchars($meta['consent'], ENT_QUOTES, 'UTF-8');
        $name    = htmlspecialchars(ucfirst($this->provider), ENT_QUOTES, 'UTF-8');

        $placeholder = sprintf(
            '<div class="embed__placeholder"><button type="button" class="embed__button embed__button--play" aria-label="%s"></button></div>',
            $label
        );

        $consentBlock = sprintf(
            '<div class="embed__consent"><button type="button" class="embed__button embed__button--consent" data-consent="%s" aria-label="Enable %s content"></button><p class="embed__consent-text">Enable content from %s.</p></div>',
            $consent,
            $name,
            $name
        );

        return $iframe . $placeholder . $consentBlock;
    }

    /**
     * Options:
     *   lazyframe (bool) default true  — dispatches to renderPlaceholder() vs renderIframe(),
     *                    and sets data-state="locked" on the outer element when true
     *   consent (bool)   default false — reserved, not wired to output. Consent gating is
     *                    client-side (site JS reading data-consent off .embed__button--consent
     *                    against its CMP), not server-rendered.
     */
    public function ___render(array $options = []): string
    {
        $default_options = [
            'lazyframe' => true,
            'consent'   => false,
        ];

        $options = array_merge([

        ], $options);

        $body = !empty($options['lazyframe'])
            ? $this->renderPlaceholder($options)
            : $this->renderIframe($options);

        if (empty($body)) {
            return '';
        }

        $safeTitle = htmlspecialchars((string) $this->getLanguageValue('title') ?: 'Embedded content', ENT_QUOTES, 'UTF-8');
        $body = str_replace('{title}', $safeTitle, $body);

        $title         = (string) $this->getLanguageValue('title');
        $description   = (string) $this->getLanguageValue('description');
        $showTitleMode = (int) $this->showtitle;

        $hasCaption = ($showTitleMode === FieldtypeEmbed::titleSeparate && !empty($description))
            || ($showTitleMode === FieldtypeEmbed::titleShow && !empty($title));

        $tag   = $hasCaption ? 'figure' : 'div';
        $state = !empty($options['lazyframe']) ? ' data-state="locked"' : '';
        $open  = sprintf(
            '<%s class="embed embed--%s"%s>',
            $tag,
            htmlspecialchars($this->provider, ENT_QUOTES, 'UTF-8'),
            $state
        );

        $caption = '';
        if ($hasCaption) {
            $text = $showTitleMode === FieldtypeEmbed::titleSeparate
                ? $description
                : htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $caption = sprintf('<figcaption class="embed__caption">%s</figcaption>', $text);
        }

        return $open . $body . $caption . "</{$tag}>";
    }

    public static function assets(){

    }
}