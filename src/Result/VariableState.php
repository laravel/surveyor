<?php

namespace Laravel\StaticAnalyzer\Result;

use Laravel\StaticAnalyzer\Types\Contracts\Type;

class VariableState
{
    public function __construct(
        public string $name,
        public Type $type,
        public int $lineNumber,
        public string $pathId,
    ) {
        //
    }

    public function __toString(): string
    {
        return "{$this->type} (from path {$this->pathId} at line {$this->lineNumber})";
    }
}
