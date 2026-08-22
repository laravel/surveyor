<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Analysis\Scope;
use RuntimeException;
use Throwable;

use function Illuminate\Filesystem\join_paths;

class AnalyzedCache
{
    /**
     * The shape of what gets written to disk. Bump it whenever a serialized
     * class changes, so an upgrade reads its own entries and not ones left by
     * a version whose objects no longer unserialize cleanly.
     */
    public const SCHEMA = 5;

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
     * Whether each frame took part in a cycle, whether by giving up on a file
     * itself or by asking one that did.
     *
     * @var list<bool>
     */
    protected static array $frameTainted = [];

    /**
     * Whether each frame gave up on a file itself. Those are the files that
     * resolved something against nothing, so they are where a second look
     * starts.
     *
     * @var list<bool>
     */
    protected static array $frameBailed = [];

    /** Index of the outermost frame taking part in an unresolved cycle. */
    protected static ?int $cycleFloor = null;

    /** @var list<array{path: string, scope: Scope, mtime: int, bailed: bool}> */
    protected static array $deferred = [];

    /**
     * Members of cycles that have just closed. Each was analyzed while another
     * member was still open, so part of its work was resolved against nothing,
     * either its own or that of a file it asked.
     *
     * @var list<array{path: string, bailed: bool}>
     */
    protected static array $settled = [];

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

    /**
     * The surface hash of each entry seen this run, whether it was analyzed or
     * read back from disk.
     *
     * @var array<string, string>
     */
    protected static array $surfaces = [];

    /**
     * How to find out what a file's surface is now. Analyzing it is the only
     * way, and only the analyzer can do that, so it hands this in.
     *
     * @var (callable(string): ?string)|null
     */
    protected static $surfaceResolver = null;

    /**
     * The record written beside each entry: its own time and surface, and the
     * files it reached with the surface each of them had at the time. Small
     * enough to read while deciding whether the entry still holds, which is
     * the point of keeping it out of the entry itself.
     *
     * @var array<string, array{schema: int, mtime: int, surface: string, dependencies: list<array{path: string, mtime: int, surface: string|null}>}|false>
     */
    protected static array $records = [];

    /**
     * Whether each path's entry still holds, worked out once per run.
     *
     * @var array<string, bool>
     */
    protected static array $current = [];

    protected static bool $fileTimesFrozen = false;

    protected static ?string $key = null;

    public static function setKey(string $key): void
    {
        static::$key = $key;
    }

    /**
     * Record that the analysis in progress reached the given path.
     *
     * Only the files a file reaches itself are recorded. What those files
     * reached in turn is their own business: an entry is validated against the
     * surface of each file it names, and a change further down the graph only
     * matters here if it moved one of those surfaces.
     */
    public static function addDependency(string $path): void
    {
        if (static::$frames === []) {
            return;
        }

        static::$frames[array_key_last(static::$frames)][$path] = true;
    }

    public static function beginAnalysis(string $path): void
    {
        static::$frames[] = [];
        static::$framePaths[] = $path;
        static::$frameTainted[] = false;
        static::$frameBailed[] = false;
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
        static::$frameBailed[array_key_last(static::$frameBailed)] = true;

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
     * Close the analysis of a path and keep the files it reached.
     */
    public static function endAnalysis(string $path): void
    {
        if (static::$frames === []) {
            return;
        }

        $index = array_key_last(static::$frames);

        $dependencies = array_pop(static::$frames);
        $tainted = array_pop(static::$frameTainted);
        array_pop(static::$frameBailed);
        array_pop(static::$framePaths);

        unset($dependencies[$path]);

        static::$dependencies[$path] = $dependencies;

        // The caller already recorded this path when it asked for it, and it
        // does not inherit what this path reached.
        if ($tainted && static::$frames !== []) {
            static::$frameTainted[array_key_last(static::$frameTainted)] = true;
        }

        if ($index === static::$cycleFloor) {
            static::$cycleFloor = null;
            static::flushDeferred();
        }
    }

    /**
     * Write out the entries held back while a cycle was open.
     *
     * Each of them reached a file that was still being analyzed, so each is
     * listed for another look once the cycle has closed. Their own edges are
     * complete: a file is recorded as reached before the analysis discovers it
     * cannot be finished.
     */
    protected static function flushDeferred(): void
    {
        $deferred = static::$deferred;
        static::$deferred = [];

        foreach ($deferred as $entry) {
            static::$settled[] = ['path' => $entry['path'], 'bailed' => $entry['bailed']];

            if (static::$persistToDisk) {
                static::persistToDisk(
                    $entry['path'],
                    $entry['scope'],
                    $entry['mtime'],
                    static::$dependencies[$entry['path']] ?? [],
                );
            }
        }
    }

    /**
     * Hand back the members of any cycle that has closed since this was last
     * called, and forget them. Each says whether it gave up on a file itself.
     *
     * @return list<array{path: string, bailed: bool}>
     */
    public static function takeSettled(): array
    {
        $settled = static::$settled;
        static::$settled = [];

        return $settled;
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
            static::$deferred[] = [
                'path' => $path,
                'scope' => $analyzed,
                'mtime' => $mtime,
                'bailed' => static::$frameBailed[array_key_last(static::$frameBailed)] ?? false,
            ];

            return;
        }

        if (static::$persistToDisk) {
            static::persistToDisk($path, $analyzed, $mtime, static::pendingDependencies($path));
        }
    }

    /**
     * The files a path reached, as recorded when it was analyzed.
     *
     * @return list<string>
     */
    public static function dependenciesOf(string $path): array
    {
        return array_keys(static::$dependencies[$path] ?? []);
    }

    /**
     * @param  (callable(string): ?string)|null  $resolver
     */
    public static function resolveSurfaceUsing(?callable $resolver): void
    {
        static::$surfaceResolver = $resolver;
    }

    /**
     * The hash of what dependents can see of a path, without unserializing its
     * entry. An analyzed entry answers from memory; anything else is read from
     * the small file written alongside the entry.
     */
    public static function surfaceHash(string $path): ?string
    {
        if (isset(static::$surfaces[$path])) {
            return static::$surfaces[$path];
        }

        // Reading the record beats hashing an entry that was loaded from disk,
        // which is why it is written in the first place.
        if ($record = static::readRecord($path)) {
            return static::$surfaces[$path] = $record['surface'];
        }

        if (isset(static::$cached[$path])) {
            return static::$surfaces[$path] = Surface::hash(static::$cached[$path]);
        }

        return null;
    }

    /**
     * Whether the stored entry for a path still describes the source.
     *
     * True when the file has not changed and nothing it reached has changed in
     * a way its dependents could see. Worked out from the records alone, so an
     * unchanged file costs a few small reads rather than unserializing a graph.
     */
    public static function isCurrent(string $path): bool
    {
        if (array_key_exists($path, static::$current)) {
            return static::$current[$path];
        }

        // Recorded graphs contain cycles. While a path is being decided it
        // counts as holding: every member is also checked on its own terms, so
        // a member that has really moved is caught there.
        static::$current[$path] = true;

        return static::$current[$path] = static::decide($path);
    }

    protected static function decide(string $path): bool
    {
        $record = static::readRecord($path);

        if ($record === null) {
            return false;
        }

        if (static::modifiedTime($path) !== $record['mtime']) {
            return false;
        }

        foreach ($record['dependencies'] as $dependency) {
            if (! static::dependencyStillHolds($dependency)) {
                return false;
            }
        }

        return true;
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

        if (($data['schema'] ?? null) !== static::SCHEMA) {
            static::invalidate($path);

            return null;
        }

        if ($data['mtime'] !== $currentModifiedTime) {
            static::invalidate($path);

            return null;
        }

        if (! static::isCurrent($path)) {
            static::invalidate($path);

            return null;
        }

        $record = static::readRecord($path);

        static::$dependencies[$path] = array_fill_keys(
            array_column($record['dependencies'] ?? [], 'path'),
            true,
        );

        $serialized = $data['scope'];
        unset($data);

        static::$cached[$path] = $serialized;
        static::$fileTimes[$path] = $currentModifiedTime;

        return static::$cached[$path];
    }

    /**
     * Whether a recorded dependency still shows the surface it showed when the
     * entry was written.
     *
     * An untouched file whose own dependencies are untouched cannot have moved,
     * so it needs nothing further. Anything else has to be analyzed to find out
     * whether the change reached its surface. If it did not, every entry that
     * named it still holds, which is the whole point.
     *
     * @param  array{path: string, mtime: int|null, surface: string|null}  $dependency
     */
    protected static function dependencyStillHolds(array $dependency): bool
    {
        $currentMtime = static::modifiedTime($dependency['path']);

        if ($currentMtime === null) {
            return false;
        }

        if ($currentMtime === $dependency['mtime']) {
            // A file with no record of its own has nothing underneath it to
            // check, so its own bytes are the whole answer.
            if (static::readRecord($dependency['path']) === null) {
                return true;
            }

            if (static::isCurrent($dependency['path'])) {
                return true;
            }
        }

        if ($dependency['surface'] === null) {
            return false;
        }

        return static::freshSurface($dependency['path']) === $dependency['surface'];
    }

    /**
     * The surface of a path as it is now, analyzing it if that is what it takes.
     */
    protected static function freshSurface(string $path): ?string
    {
        if (isset(static::$cached[$path])) {
            return static::surfaceHash($path);
        }

        if (static::$surfaceResolver === null) {
            return null;
        }

        return (static::$surfaceResolver)($path);
    }

    protected static function getCacheFilePayload(string $cacheFile, string $path): ?string
    {
        $serialized = static::verify(file_get_contents($cacheFile));

        if ($serialized === null) {
            static::invalidate($path);
        }

        return $serialized;
    }

    public static function invalidate(string $path): void
    {
        unset(
            static::$cached[$path],
            static::$fileTimes[$path],
            static::$surfaces[$path],
            static::$records[$path],
        );

        static::$current[$path] = false;

        if (static::$persistToDisk) {
            foreach ([static::getCacheFilePath($path), static::getRecordFilePath($path)] as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
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
        static::$frameBailed = [];
        static::$cycleFloor = null;
        static::$deferred = [];
        static::$settled = [];
        static::$modifiedTimes = [];
        static::$surfaces = [];
        static::$records = [];
        static::$current = [];
    }

    public static function clear(): void
    {
        static::clearMemory();

        if (static::$cacheDirectory && is_dir(static::$cacheDirectory)) {
            $files = [
                ...glob(static::$cacheDirectory.'/*.cache'),
                ...glob(static::$cacheDirectory.'/*.record'),
            ];

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

        $serialized = static::sign(serialize([
            'schema' => static::SCHEMA,
            'mtime' => $mtime,
            'scope' => $analyzed,
        ]));

        file_put_contents($cacheFile, $serialized);

        static::$surfaces[$path] = Surface::hash($analyzed);

        static::writeRecord($path, $mtime, $dependencies);
    }

    /**
     * Store what the entry has to be judged against: the file's own time and
     * surface, and every file it reached with the surface that file showed.
     *
     * A file reached while its own analysis was still open has no surface yet.
     * It is recorded without one, which makes anything naming it invalid as
     * soon as it changes. Those entries are analyzed again once the cycle
     * closes, and the record written then has the real surfaces.
     *
     * @param  array<string, true>  $dependencies
     */
    protected static function writeRecord(string $path, int $mtime, array $dependencies): void
    {
        $recorded = [];

        foreach (array_keys($dependencies) as $dependency) {
            $dependencyMtime = static::modifiedTime($dependency);

            if ($dependencyMtime === null) {
                continue;
            }

            $recorded[] = [
                'path' => $dependency,
                'mtime' => $dependencyMtime,
                'surface' => static::surfaceHash($dependency),
            ];
        }

        static::$records[$path] = [
            'schema' => static::SCHEMA,
            'mtime' => $mtime,
            'surface' => static::$surfaces[$path],
            'dependencies' => $recorded,
        ];

        static::$current[$path] = true;

        file_put_contents(
            static::getRecordFilePath($path),
            static::sign(serialize(static::$records[$path])),
        );
    }

    /**
     * @return array{schema: int, mtime: int, surface: string, dependencies: list<array{path: string, mtime: int, surface: string|null}>}|null
     */
    protected static function readRecord(string $path): ?array
    {
        if (array_key_exists($path, static::$records)) {
            return static::$records[$path] ?: null;
        }

        if (! static::$persistToDisk) {
            return null;
        }

        $recordFile = static::getRecordFilePath($path);

        if (! file_exists($recordFile)) {
            static::$records[$path] = false;

            return null;
        }

        $serialized = static::verify(file_get_contents($recordFile));

        try {
            $record = $serialized === null ? null : unserialize($serialized);
        } catch (Throwable) {
            $record = null;
        }

        if (! is_array($record)
            || ($record['schema'] ?? null) !== static::SCHEMA
            || ! isset($record['mtime'], $record['surface'], $record['dependencies'])
        ) {
            static::$records[$path] = false;

            return null;
        }

        return static::$records[$path] = $record;
    }

    protected static function sign(string $serialized): string
    {
        if (! static::$key) {
            return $serialized;
        }

        return hash_hmac('sha256', $serialized, static::$key).':'.$serialized;
    }

    protected static function verify(string $content): ?string
    {
        if (! static::$key) {
            return $content;
        }

        if (! str_contains($content, ':')) {
            return null;
        }

        [$signature, $serialized] = explode(':', $content, 2);

        return hash_equals($signature, hash_hmac('sha256', $serialized, static::$key))
            ? $serialized
            : null;
    }

    protected static function getCacheFilePath(string $path): string
    {
        return static::$cacheDirectory.'/'.md5($path).'.cache';
    }

    protected static function getRecordFilePath(string $path): string
    {
        return static::$cacheDirectory.'/'.md5($path).'.record';
    }
}
