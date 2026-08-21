<?php

namespace App\Attributes;

use Attribute;
use Laravel\Surveyor\Contracts\ConditionallyIgnored;

#[Attribute(Attribute::TARGET_ALL)]
class ConditionalIgnore implements ConditionallyIgnored
{
    public function __construct(
        public readonly string|array|null $unless = null,
        public readonly string|array|null $when = null,
    ) {
        //
    }

    public function unless(): string|array|null
    {
        return $this->unless;
    }

    public function when(): string|array|null
    {
        return $this->when;
    }
}
