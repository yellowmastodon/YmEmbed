<?php

namespace YmEmbed;

use ProcessWire\WireData;
use ProcessWire\Language;

/**
 * @property string $provider
 * @property string $url
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
        'html'          => ['db' => 'data',          'type' => 'raw',      'schema' => 'MEDIUMTEXT'],
        'title'         => ['db' => 'title',         'type' => 'text',     'schema' => 'TEXT', 'multilang' => true],
        'thumbnail_url' => ['db' => 'thumbnail_url', 'type' => 'url',      'schema' => 'TEXT'],
        'aspect_ratio'  => ['db' => 'aspect_ratio',  'type' => 'float',    'schema' => 'DECIMAL(8,6) NOT NULL DEFAULT 1.777778'],
        'showtitle'     => ['db' => 'showtitle',     'type' => 'int',      'schema' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1'],
        'description'   => ['db' => 'description',   'type' => 'textarea', 'schema' => 'TEXT', 'multilang' => true],
        'width'         => ['db' => null,            'type' => 'int',      'schema' => null],
        'height'        => ['db' => null,            'type' => 'int',      'schema' => null],
    ];

    public function __construct(array $data = [])
    {
        // Set defaults
        $this->set('provider', '');
        $this->set('url', '');
        $this->set('html', '');
        $this->set('title', '');
        $this->set('thumbnail_url', '');
        $this->set('aspect_ratio', 1.777778);
        $this->set('showtitle', FieldtypeEmbed::titleShow);
        $this->set('description', '');

        if (!empty($data)) {
            $this->setArray($data);
        }
    }

    /**
     * Get property value with explicit or current language support.
     *
     * @param string $key
     * @param Language|int|string|null $language
     * @return mixed
     */
    public function get($key, $language = null)
    {
        // If property is not multilang or has an explicit language suffix (e.g. title1023), return directly
        if (!self::isMultilangProperty($key) || preg_match('/\d+$/', $key)) {
            return parent::get($key);
        }

        return $this->getLanguageValue($key, $language);
    }

    /**
     * Set a property value with optional language support.
     *
     * @param string $key
     * @param mixed $value
     * @param Language|int|string|null $language
     * @return $this
     */
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

    /**
     * Get a property value for a specific language or active language with fallback to default.
     *
     * @param string $key Property base name (e.g. 'title', 'description')
     * @param Language|int|string|null $language Language object, ID, or name (null for user active lang)
     * @return mixed
     */
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

    /**
     * Set property value for a specific language.
     *
     * @param string $key
     * @param mixed $value
     * @param Language|int|string|null $language
     * @return $this
     */
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

    /**
     * Helper to resolve language input into a ProcessWire Language object.
     *
     * @param Language|int|string|null $language
     * @return Language|null
     */
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

    /**
     * Check if a key is allowed by schema or matches dynamic multi-language key format.
     */
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

    /**
     * Check if a property is multi-language enabled in SCHEMA.
     */
    public static function isMultilangProperty(string $key): bool
    {
        return !empty(self::SCHEMA[$key]['multilang']);
    }

    public static function allowedProperties(): array
    {
        return array_keys(self::SCHEMA);
    }

    public function render(array $options = []): string
    {
        if (empty($this->html)) {
            return '';
        }

        $title       = (string) $this->getLanguageValue('title');
        $description = (string) $this->getLanguageValue('description');

        $safeTitle = htmlspecialchars($title ?: 'Embedded content', ENT_QUOTES, 'UTF-8');
        $html      = str_replace('{title}', $safeTitle, $this->html);

        $showTitleMode = (int) $this->get('showtitle');

        if ($showTitleMode === FieldtypeEmbed::titleSeparate && !empty($description)) {
            return sprintf(
                '<figure class="embed-figure">%s<figcaption class="embed-figure__caption">%s</figcaption></figure>',
                $html,
                $description
            );
        }

        if ($showTitleMode === FieldtypeEmbed::titleShow && !empty($title)) {
            return sprintf(
                '<figure class="embed-figure">%s<figcaption class="embed-figure__caption">%s</figcaption></figure>',
                $html,
                htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            );
        }

        return $html;
    }

}