<?php

namespace Laravel\Surveyor\Types\Entities;

use Laravel\Surveyor\Types\Contracts\Type;
use Laravel\Surveyor\Types\StringType;

class JsonEncoded extends StringType
{
    public function __construct(public readonly Type $data)
    {
        parent::__construct();
    }

    public function id(): string
    {
        return 'json:'.$this->data->id();
    }

    public function isMoreSpecificThan(Type $type): bool
    {
        return $type::class === StringType::class && $type->value === null;
    }
}
