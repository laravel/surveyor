<?php

namespace App\Determinism\Cycle;

class Beta
{
    public static function make(): static
    {
        return new static;
    }

    /**
     * Calling into Alpha analyzes it while this file is still open, which is
     * what puts Alpha in a cycle with this one.
     */
    public function touch(): string
    {
        return (new Alpha)->label();
    }
}
