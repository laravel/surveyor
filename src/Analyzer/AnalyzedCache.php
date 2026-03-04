<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Analysis\Scope;
use RuntimeException;

use function Illuminate\Filesystem\join_paths;

class AnalyzedCache
{
    protected static array $cached = [];

    protected static array $fileTimes = [];

    protected static array $inProgress = [];

    protected static ?string $cacheDirectory = null;

    protected static bool $persistToDisk = false;

    protected static array $dependencies = [];

    protected static ?string $key = null;

    public static function setKey(string $key): void
    {
        static::$key = $key;
    }

    public static function addDependency(string $path): void
    {
        static::$dependencies[] = $path;
    }

    public static function setCacheDirectory(string $directory): void
    {
        static::$cacheDirectory = $directory;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $gitIgnorePath = join_paths($directory, '.gitignore');

        if (! file_exists($gitIgnorePath)) {
            file_put_contents($gitIgnorePath, "*\n!.gitignore\n");
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

        $content = file_get_contents($cacheFile);

        if (static::$key) {
            if (! str_contains($content, ':')) {
                static::invalidate($path);

                return null;
            }

            [$signature, $serialized] = explode(':', $content, 2);

            if (! hash_equals($signature, hash_hmac('sha256', $serialized, static::$key))) {
                static::invalidate($path);

                return null;
            }
        } else {
            $serialized = $content;
        }

        $data = unserialize($serialized);

        if (! is_array($data) || ! isset($data['mtime'], $data['scope'])) {
            return null;
        }

        if ($data['mtime'] !== $currentModifiedTime) {
            static::invalidate($path);

            return null;
        }

        foreach ($data['dependencies'] as $dependency) {
            if ($dependency['mtime'] !== filemtime($dependency['path'])) {
                static::invalidate($dependency['path']);
                static::invalidate($path);

                return null;
            }
        }

        $serialized = $data['scope'];
        unset($data);

        static::$cached[$path] = $serialized;
        static::$fileTimes[$path] = $currentModifiedTime;

        return static::$cached[$path];
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

        if (static::$cacheDirectory && is_dir(static::$cacheDirectory)) {
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
            'dependencies' => array_values(array_filter(array_map(fn ($dep) => [
                'path' => $dep,
                'mtime' => file_exists($dep) ? filemtime($dep) : null,
            ], array_values(array_unique(self::$dependencies))), fn ($dep) => $dep['mtime'] !== null)),
            'scope' => $analyzed,
        ];

        $serialized = serialize($data);

        if (static::$key) {
            $serialized = hash_hmac('sha256', $serialized, static::$key).':'.$serialized;
        }

        file_put_contents($cacheFile, $serialized);
    }

    protected static function getCacheFilePath(string $path): string
    {
        return static::$cacheDirectory.'/'.md5($path).'.cache';
    }
}
