<?php

namespace App\Support;

final readonly class Dimensions
{
    public function __construct(
        public int $width,
        public int $height,
    ) {}
}
