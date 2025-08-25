<?php

namespace Laravel\StaticAnalyzer\Result;

use Laravel\StaticAnalyzer\Types\Contracts\Type;

class Param extends AbstractResult
{
    public function __construct(
        public string $name,
        public ?Type $type,
        public bool $isVariadic,
        public bool $isReference,
    ) {
        //
    }
}
