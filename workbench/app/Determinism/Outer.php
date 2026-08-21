<?php

namespace App\Determinism;

use App\Determinism\Types\Marker;

class Outer
{
    /**
     * Calling into another class makes the analyzer stop and analyze that
     * file, so the method below is reached with another file's analysis
     * already finished.
     */
    public function boot(): string
    {
        return (new Nested)->name();
    }

    public function stamp(?Marker $marker): ?Marker
    {
        return $marker;
    }
}
