<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Analysis\Scope;

class AnalyzedCache
{
    // TODO: Probably implemenent recently used strategy to keep a limit of cache entries
    protected static array $cached = [];

    public static function add(string $path, Scope $analyzed): void
    {
        static::$cached[$path] = $analyzed;
    }

    public static function get(string $path): ?Scope
    {
        return static::$cached[$path] ?? null;
    }
}
