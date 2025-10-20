<?php

namespace Laravel\Surveyor\Analyzed;

use Laravel\Surveyor\Types\Contracts\Type;

class ConstantResult
{
    public function __construct(
        public readonly string $name,
        public readonly ?Type $type,
    ) {
        //
    }
}
