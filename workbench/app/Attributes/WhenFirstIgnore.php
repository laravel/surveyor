<?php

namespace App\Attributes;

use Attribute;
use Laravel\Surveyor\Contracts\ConditionallyIgnored;

/**
 * Declares its conditions the other way round, to prove a positional argument
 * is read from the marker's own constructor rather than an assumed order.
 */
#[Attribute(Attribute::TARGET_ALL)]
class WhenFirstIgnore implements ConditionallyIgnored
{
    public function __construct(
        public readonly string|array|null $when = null,
        public readonly string|array|null $unless = null,
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
