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

    /**
     * Dependencies collected for each analysis currently on the stack. The
     * innermost frame belongs to the file being analyzed right now.
     *
     * @var list<array<string, true>>
     */
    protected static array $frames = [];

    /** @var list<string> */
    protected static array $framePaths = [];

    /**
     * Whether each frame skipped a file that was still being analyzed, which
     * leaves its dependency list incomplete.
     *
     * @var list<bool>
     */
    protected static array $frameTainted = [];

    /** Index of the outermost frame taking part in an unresolved cycle. */
    protected static ?int $cycleFloor = null;

    /** @var list<array{path: string, scope: Scope, mtime: int}> */
    protected static array $deferred = [];

    /**
     * Every file each analyzed path depends on, directly or otherwise. Stored
     * as a closure rather than direct edges so a cache entry can be validated
     * by stat'ing a flat list, without walking the graph.
     *
     * @var array<string, array<string, true>>
     */
    protected static array $dependencies = [];

    /** @var array<string, int|null> */
    protected static array $modifiedTimes = [];

    protected static bool $fileTimesFrozen = false;

    protected static ?string $key = null;

    public static function setKey(string $key): void
    {
        static::$key = $key;
    }

    /**
     * Record that the analysis in progress depends on the given path, along
     * with everything that path itself depends on.
     */
    public static function addDependency(string $path): void
    {
        if (static::$frames === []) {
            return;
        }

        $frame = array_key_last(static::$frames);

        static::$frames[$frame][$path] = true;

        // A path resolved from cache is never pushed as a frame of its own, so
        // its dependencies have to be folded in here or they are lost.
        if (isset(static::$dependencies[$path])) {
            static::$frames[$frame] += static::$dependencies[$path];
        }
    }

    public static function beginAnalysis(string $path): void
    {
        static::$frames[] = [];
        static::$framePaths[] = $path;
        static::$frameTainted[] = false;
    }

    /**
     * Record that the analysis in progress gave up on a file that is already
     * being analyzed further up the stack. Everything between that file and
     * here is mutually dependent, so none of it can be cached until the
     * outermost member finishes and the full closure is known.
     */
    public static function noteCycle(string $path): void
    {
        if (static::$frames === []) {
            return;
        }

        static::$frameTainted[array_key_last(static::$frameTainted)] = true;

        // The file should always be somewhere on the stack, since that is what
        // makes it in progress. Fall back to the outermost frame rather than
        // caching a dependency list that is missing whatever it reached.
        $root = array_search($path, static::$framePaths, true);

        if ($root === false) {
            $root = 0;
        }

        static::$cycleFloor = static::$cycleFloor === null
            ? $root
            : min(static::$cycleFloor, $root);
    }

    /**
     * Close the analysis of a path, keep its dependency closure, and fold that
     * closure into the analysis that asked for it.
     */
    public static function endAnalysis(string $path): void
    {
        if (static::$frames === []) {
            return;
        }

        $index = array_key_last(static::$frames);

        $dependencies = array_pop(static::$frames);
        $tainted = array_pop(static::$frameTainted);
        array_pop(static::$framePaths);

        unset($dependencies[$path]);

        static::$dependencies[$path] = $dependencies;

        if (static::$frames !== []) {
            $frame = array_key_last(static::$frames);
            static::$frames[$frame] += $dependencies;

            if ($tainted) {
                static::$frameTainted[$frame] = true;
            }
        }

        if ($index === static::$cycleFloor) {
            static::$cycleFloor = null;
            static::flushDeferred($dependencies, $path);
        }
    }

    /**
     * Write out the entries held back while a cycle was open. Every member of
     * the cycle gets the closure of its outermost file plus every other member,
     * since a change to any of them could change all of them.
     *
     * @param  array<string, true>  $dependencies
     */
    protected static function flushDeferred(array $dependencies, string $root): void
    {
        $deferred = static::$deferred;
        static::$deferred = [];

        $dependencies[$root] = true;

        foreach ($deferred as $entry) {
            $dependencies[$entry['path']] = true;
        }

        foreach ($deferred as $entry) {
            $own = $dependencies;
            unset($own[$entry['path']]);

            static::$dependencies[$entry['path']] = $own;

            if (static::$persistToDisk) {
                static::persistToDisk($entry['path'], $entry['scope'], $entry['mtime'], $own);
            }
        }
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

        if ($mtime === null) {
            return;
        }

        // Its dependency list is still missing whatever the cycle resolves to.
        if (static::$cycleFloor !== null && (static::$frameTainted[array_key_last(static::$frameTainted)] ?? false)) {
            static::$deferred[] = ['path' => $path, 'scope' => $analyzed, 'mtime' => $mtime];

            return;
        }

        if (static::$persistToDisk) {
            static::persistToDisk($path, $analyzed, $mtime, static::pendingDependencies($path));
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

        $dependencies = [];

        foreach ($data['dependencies'] as $dependency) {
            $currentMtime = static::modifiedTime($dependency['path']);

            if ($dependency['mtime'] !== $currentMtime) {
                static::invalidate($dependency['path']);
                static::invalidate($path);

                return null;
            }

            $dependencies[$dependency['path']] = true;
        }

        // Whoever asked for this path inherits its dependencies, so a change
        // further down the graph still invalidates the files above it.
        static::$dependencies[$path] = $dependencies;

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
        static::$frames = [];
        static::$framePaths = [];
        static::$frameTainted = [];
        static::$cycleFloor = null;
        static::$deferred = [];
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

    /**
     * The dependency closure for a path that is being cached. Entries are
     * written while their frame is still open, so the open frame is the
     * authoritative list.
     *
     * @return array<string, true>
     */
    protected static function pendingDependencies(string $path): array
    {
        $dependencies = static::$frames === []
            ? (static::$dependencies[$path] ?? [])
            : static::$frames[array_key_last(static::$frames)];

        unset($dependencies[$path]);

        return $dependencies;
    }

    /**
     * @param  array<string, true>  $dependencies
     */
    protected static function persistToDisk(string $path, Scope $analyzed, int $mtime, array $dependencies): void
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
            ], array_keys($dependencies)), fn ($dep) => $dep['mtime'] !== null)),
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
