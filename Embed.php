<?php

namespace YmEmbed;

use ProcessWire\WireData;



/**
 * @property string $provider
 * @property string $video_id
 * @property string $url
 * @property string $html
 * @property string $title
 * @property string $thumbnail_url
 * @property ?int $width
 * @property ?int $height
 * @property object $author
 * @property string $description
 */
class Embed extends WireData
{

    public const SCHEMA = [
        'provider'             => ['db' => 'provider'],
        'video_id'              => ['db' => 'video_id'],
        'url'                   => ['db' => 'url'],
        'html'                  => ['db' => 'data'],
        'title'                 => ['db' => 'title'],
        'thumbnail_url'         => ['db' => 'thumbnail_url'],
        'aspect_ratio'          => ['db' => 'aspect_ratio'],
        'description'           => ['db' => 'description'],
        'description_override'  => ['db' => 'description_override'],
        'width'                 => ['db' => null], // runtime only
        'height'                => ['db' => null], // runtime only
        'author'                => ['db' => null], // handled specially, see AUTHOR_SCHEMA
    ];

    public const AUTHOR_SCHEMA = [
        'name' => 'author_name',
        'url'  => 'author_url',
    ];


    public function __construct(array $data = []) {
        $this->set('provider', '');
        $this->set('video_id', '');
        $this->set('url', '');

        $this->set('html', '');

        $this->set('title', '');
        $this->set('thumbnail_url', '');
        $this->set('aspect_ratio', 0);
        $this->set('description', '');
        $this->set('description_override', '');

        $this->set('author', new Author());

        if (isset($data['author']) && is_array($data['author'])) {
            $data['author'] = new Author($data['author']);
        }

        $this->setArray(
            array_intersect_key(
                $data,
                array_flip($this->allowedProperties)
            )
        );
    }
}
