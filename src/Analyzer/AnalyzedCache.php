<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Analysis\Scope;
use RuntimeException;

class AnalyzedCache
{
    // TODO: Probably implemenent recently used strategy to keep a limit of cache entries
    protected static array $cached = [];

    protected static array $fileTimes = [];

    protected static array $inProgress = [];

    protected static ?string $cacheDirectory = null;

    protected static bool $persistToDisk = false;

    public static function setCacheDirectory(string $directory): void
    {
        static::$cacheDirectory = $directory;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public static function enable(): void
    {
        if (static::$cacheDirectory === null) {
            throw new RuntimeException('Cache directory must be set before enabling disk cache. Call setCacheDirectory() first.');
        }

        static::$persistToDisk = true;
    }

    public static function disable(): void
    {
        static::$persistToDisk = false;
    }

    public static function enableDiskCache(string $directory): void
    {
        static::setCacheDirectory($directory);
        static::enable();
    }

    public static function add(string $path, Scope $analyzed): void
    {
        $mtime = file_exists($path) ? filemtime($path) : null;

        static::$cached[$path] = $analyzed;
        static::$fileTimes[$path] = $mtime;
        unset(static::$inProgress[$path]);

        if (static::$persistToDisk && $mtime !== null) {
            static::persistToDisk($path, $analyzed, $mtime);
        }
    }

    public static function get(string $path): ?Scope
    {
        if (! file_exists($path)) {
            return null;
        }

        $currentModifiedTime = filemtime($path);

        return self::tryFromMemory($path, $currentModifiedTime)
            ?? self::tryFromDisk($path, $currentModifiedTime)
            ?? null;
    }

    protected static function tryFromMemory(string $path, int $currentModifiedTime): ?Scope
    {
        if (! isset(static::$cached[$path])) {
            return null;
        }

        $cachedModifiedTime = static::$fileTimes[$path] ?? null;

        if ($cachedModifiedTime === $currentModifiedTime) {
            return static::$cached[$path];
        }

        static::invalidate($path);

        return null;
    }

    protected static function tryFromDisk(string $path, int $currentModifiedTime): ?Scope
    {
        if (! static::$persistToDisk) {
            return null;
        }

        $cacheFile = static::getCacheFilePath($path);

        if (! file_exists($cacheFile)) {
            return null;
        }

        $data = unserialize(file_get_contents($cacheFile));

        if (! is_array($data) || ! isset($data['mtime'], $data['scope'])) {
            return null;
        }

        if ($data['mtime'] !== $currentModifiedTime) {
            static::invalidate($path);

            return null;
        }

        $serialized = $data['scope'];
        unset($data);

        $scope = unserialize($serialized);
        unset($serialized);

        static::$cached[$path] = $scope;
        static::$fileTimes[$path] = $currentModifiedTime;

        return $scope;
    }

    public static function invalidate(string $path): void
    {
        unset(static::$cached[$path], static::$fileTimes[$path]);

        if (static::$persistToDisk) {
            $cacheFile = static::getCacheFilePath($path);
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
    }

    public static function clearMemory(): void
    {
        static::$cached = [];
        static::$fileTimes = [];
        static::$inProgress = [];
    }

    public static function clear(): void
    {
        static::clearMemory();

        if (static::$persistToDisk && static::$cacheDirectory && is_dir(static::$cacheDirectory)) {
            $files = glob(static::$cacheDirectory.'/*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    public static function inProgress(string $path): void
    {
        self::$inProgress[$path] = true;
    }

    public static function isInProgress(string $path): bool
    {
        return self::$inProgress[$path] ?? false;
    }

    protected static function persistToDisk(string $path, Scope $analyzed, int $mtime): void
    {
        // Ensure cache directory exists
        if (! is_dir(static::$cacheDirectory)) {
            mkdir(static::$cacheDirectory, 0755, true);
        }

        $cacheFile = static::getCacheFilePath($path);
        $data = [
            'mtime' => $mtime,
            'scope' => serialize($analyzed),
        ];

        file_put_contents($cacheFile, serialize($data));
    }

    protected static function getCacheFilePath(string $path): string
    {
        return static::$cacheDirectory.'/'.md5($path).'.cache';
    }
}
