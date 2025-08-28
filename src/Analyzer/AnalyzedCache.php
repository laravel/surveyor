<?php

namespace Laravel\StaticAnalyzer\Analyzer;

class AnalyzedCache
{
    // TODO: Probably implemenent recently used strategy to keep a limit of cache entries
    protected static array $cached = [];

    protected static array $inProgress = [];

    public static function inProgress(string $path): void
    {
        self::$inProgress[$path] = true;
    }

    public static function isInProgress(string $path): bool
    {
        return self::$inProgress[$path] ?? false;
    }

    public static function add(string $path, array $analyzed): void
    {
        dd('add!', $path);
        static::$cached[$path] = $analyzed;
        unset(static::$inProgress[$path]);
    }

    public static function get(string $path): ?array
    {
        return static::$cached[$path] ?? null;
    }
}
