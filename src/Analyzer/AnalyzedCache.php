<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Analysis\Scope;
use RuntimeException;
use Throwable;

use function Illuminate\Filesystem\join_paths;

class AnalyzedCache
{
    protected static array $cached = [];

    protected static array $fileTimes = [];

    protected static array $inProgress = [];

    protected static ?string $cacheDirectory = null;

    protected static bool $persistToDisk = false;

    /** @var array<string, true> */
    protected static array $dependencies = [];

    /** @var array<string, int|null> */
    protected static array $modifiedTimes = [];

    protected static bool $fileTimesFrozen = false;

    protected static ?string $key = null;

    public static function setKey(string $key): void
    {
        static::$key = $key;
    }

    public static function addDependency(string $path): void
    {
        // Keyed by path to avoid duplicates. The full list is written into
        // every cache file, and it only grows.
        static::$dependencies[$path] = true;
    }

    /**
     * Look up each file's modification time once and reuse it, instead of
     * stat'ing on every cache lookup.
     *
     * Off by default. Only turn it on when files cannot change while the
     * process runs, such as in a one-shot command. With it on, editing a file
     * mid-run will not invalidate its cache entry.
     */
    public static function freezeFileTimes(bool $frozen = true): void
    {
        static::$fileTimesFrozen = $frozen;

        if (! $frozen) {
            static::$modifiedTimes = [];
        }
    }

    protected static function modifiedTime(string $path): ?int
    {
        if (static::$fileTimesFrozen && array_key_exists($path, static::$modifiedTimes)) {
            return static::$modifiedTimes[$path];
        }

        $modifiedTime = file_exists($path) ? filemtime($path) : null;

        if (static::$fileTimesFrozen) {
            static::$modifiedTimes[$path] = $modifiedTime;
        }

        return $modifiedTime;
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
        $mtime = static::modifiedTime($path);

        static::$cached[$path] = $analyzed;
        static::$fileTimes[$path] = $mtime;
        unset(static::$inProgress[$path]);

        if (static::$persistToDisk && $mtime !== null) {
            static::persistToDisk($path, $analyzed, $mtime);
        }
    }

    public static function get(string $path): ?Scope
    {
        $currentModifiedTime = static::modifiedTime($path);

        if ($currentModifiedTime === null) {
            return null;
        }

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

        $serialized = self::getCacheFilePayload($cacheFile, $path);

        if ($serialized === null) {
            return null;
        }

        try {
            $data = unserialize($serialized);
        } catch (Throwable) {
            static::invalidate($path);

            return null;
        }

        if (! is_array($data) || ! isset($data['mtime'], $data['scope'])) {
            static::invalidate($path);

            return null;
        }

        if ($data['mtime'] !== $currentModifiedTime) {
            static::invalidate($path);

            return null;
        }

        foreach ($data['dependencies'] as $dependency) {
            $currentMtime = static::modifiedTime($dependency['path']);

            if ($dependency['mtime'] !== $currentMtime) {
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

    protected static function getCacheFilePayload(string $cacheFile, string $path): ?string
    {
        $content = file_get_contents($cacheFile);

        if (! static::$key) {
            return $content;
        }

        if (! str_contains($content, ':')) {
            static::invalidate($path);

            return null;
        }

        [$signature, $serialized] = explode(':', $content, 2);

        if (! hash_equals($signature, hash_hmac('sha256', $serialized, static::$key))) {
            static::invalidate($path);

            return null;
        }

        return $serialized;
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
        static::$dependencies = [];
        static::$modifiedTimes = [];
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
                'mtime' => static::modifiedTime($dep),
            ], array_keys(self::$dependencies)), fn ($dep) => $dep['mtime'] !== null)),
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
