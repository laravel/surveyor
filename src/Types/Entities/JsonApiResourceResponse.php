<?php

namespace Laravel\Surveyor\Types\Entities;

use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;

class JsonApiResourceResponse extends ClassType implements TypeContract
{
    public function __construct(
        public readonly string $resourceClass,
        public readonly ?TypeContract $attributes = null,
        public readonly ?TypeContract $relationships = null,
        public readonly ?TypeContract $links = null,
        public readonly ?TypeContract $meta = null,
        public readonly bool $isCollection = false,
    ) {
        parent::__construct($resourceClass);
    }

    public function id(): string
    {
        $id = 'jsonapi:'.$this->resourceClass;

        if ($this->attributes) {
            $id .= '::attrs:'.$this->attributes->id();
        }

        return $this->isCollection ? $id.'[]' : $id;
    }
}
