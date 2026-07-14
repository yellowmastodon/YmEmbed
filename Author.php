<?php namespace YmEmbed;

use ProcessWire\WireData;

/**
 * @property string $name
 * @property string $url
 */
class Author extends WireData
{

    protected $allowedProperties = [
        'name',
        'url'
    ];

    public function __construct(array $data = [])
    {
        
        $this->set('name', '');
        $this->set('url', '');
        
        $this->setArray(
            array_intersect_key(
                $data,
                array_flip($this->allowedProperties)
            )
        );
    }
}