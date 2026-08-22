<?php

/**
 * Dumps a canonical view of every entry in a Surveyor analysis cache, so two
 * runs can be compared without caring about object identity or key order.
 *
 * Usage: php surfacedump.php <cache-dir> [--detail]
 *
 * Without --detail: one `path<TAB>surface-hash<TAB>entry-hash` row per entry.
 * With --detail: the canonical surface lines themselves, prefixed by path.
 *
 * What counts as a surface is Surveyor's own `Analyzer\Surface`, so this script
 * and the cache cannot disagree about it.
 *
 * SURFACE_AUTOLOAD names the autoloader to boot. It has to be the one belonging
 * to the application whose cache is being read, so the classes inside it can be
 * built back up.
 */

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzer\Surface;

$autoload = getenv('SURFACE_AUTOLOAD')
    ?: exit("set SURFACE_AUTOLOAD to the vendor/autoload.php of the application being read\n");

require $autoload;

error_reporting(E_ALL & ~E_DEPRECATED);

$dir = $argv[1] ?? exit("usage: surfacedump.php <cache-dir> [--detail]\n");
$detail = in_array('--detail', $argv, true);

// Hashes each top-level Scope property on its own, so a diff says which part
// of an entry moved rather than only that it moved.
$fields = in_array('--fields', $argv, true);

/**
 * Hashes each top-level Scope property on its own. This is a diagnostic, not a
 * surface: it says which part of an entry moved, including the parts a
 * dependent cannot see.
 *
 * @return list<string>
 */
function fieldLines(Scope $scope): array
{
    $lines = [];

    foreach ((new ReflectionObject($scope))->getProperties() as $property) {
        $lines[] = 'field '.$property->getName().' '.($property->isInitialized($scope)
            ? md5(serialize($property->getValue($scope)))
            : '<uninitialized>');
    }

    return $lines;
}

function peek(object $object, string $property)
{
    $reflection = new ReflectionObject($object);

    while ($reflection && ! $reflection->hasProperty($property)) {
        $reflection = $reflection->getParentClass();
    }

    if (! $reflection) {
        return null;
    }

    $prop = $reflection->getProperty($property);

    return $prop->isInitialized($object) ? $prop->getValue($object) : null;
}

$files = array_values(array_filter(
    glob(rtrim($dir, '/').'/*.cache'),
    fn ($file) => ! str_starts_with(basename($file), 'inventory-'),
));

$rows = [];

foreach ($files as $file) {
    $content = file_get_contents($file);

    // Entries may be HMAC prefixed; the signature is not part of the payload.
    if (preg_match('/^[0-9a-f]{64}:/', $content)) {
        $content = substr($content, 65);
    }

    try {
        $data = unserialize($content);
    } catch (Throwable $e) {
        fwrite(STDERR, "unreadable: $file ({$e->getMessage()})\n");

        continue;
    }

    if (! is_array($data) || ! ($data['scope'] ?? null) instanceof Scope) {
        fwrite(STDERR, "unexpected payload: $file\n");

        continue;
    }

    $scope = $data['scope'];
    $path = peek($scope, 'path') ?? basename($file);
    $lines = $fields ? fieldLines($scope) : Surface::lines($scope);

    $rows[$path] = [
        'surface' => $fields ? md5(implode("\n", $lines)) : Surface::hash($scope),
        'entry' => md5(serialize($scope)),
        'lines' => $lines,
        'dependencies' => count($data['dependencies'] ?? []),
    ];
}

ksort($rows);

foreach ($rows as $path => $row) {
    if ($detail) {
        foreach ($row['lines'] as $line) {
            echo $path, "\t", $line, "\n";
        }

        continue;
    }

    echo $path, "\t", $row['surface'], "\t", $row['entry'], "\t", $row['dependencies'], "\n";
}
