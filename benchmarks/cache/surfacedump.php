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
 * The autoloader defaults to the Cloud checkout; override with SURFACE_AUTOLOAD.
 */

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;

require getenv('SURFACE_AUTOLOAD') ?: '/Users/joetannenbaum/Herd/cloud/vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$dir = $argv[1] ?? exit("usage: surfacedump.php <cache-dir> [--detail]\n");
$detail = in_array('--detail', $argv, true);

// Hashes each top-level Scope property on its own, so a diff says which part
// of an entry moved rather than only that it moved.
$fields = in_array('--fields', $argv, true);

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

function typeId(?TypeContract $type): string
{
    if ($type === null) {
        return '-';
    }

    try {
        $id = $type->id();
    } catch (Throwable $e) {
        $id = 'ERR:'.$e->getMessage();
    }

    return $type::class.'('.$id.')'
        .($type->isNullable() ? '|null' : '')
        .($type->isOptional() ? '|opt' : '');
}

function methodLines(string $prefix, MethodResult $method): array
{
    $lines = [];

    $parameters = [];

    foreach (peek($method, 'parameters') ?? [] as $name => $type) {
        $parameters[] = $name.':'.typeId($type);
    }

    $returns = [];

    foreach (peek($method, 'returnTypes') ?? [] as $entry) {
        $returns[] = typeId($entry['type']);
    }

    $lines[] = $prefix.' ('.implode(', ', $parameters).') -> '.implode(' + ', $returns)
        .($method->isModelRelation() ? ' [relation]' : '');

    foreach (peek($method, 'validationRules') ?? [] as $key => $rules) {
        $lines[] = $prefix.' rule '.$key.' = '.json_encode($rules);
    }

    return $lines;
}

/**
 * Everything a dependent can read off a cached entry, other than the state
 * tracker, which holds the variables of the run that produced it.
 */
function scopeLines(Scope $scope): array
{
    $lines = [
        'scope entity '.($scope->entityName() ?? '-'),
        'scope method '.($scope->methodName() ?? '-'),
        'scope namespace '.(peek($scope, 'namespace') ?? '-'),
        'scope extends '.implode(',', peek($scope, 'extends') ?? []),
        'scope implements '.implode(',', peek($scope, 'implements') ?? []),
        'scope traits '.implode(',', peek($scope, 'traits') ?? []),
    ];

    foreach (peek($scope, 'uses') ?? [] as $alias => $use) {
        $lines[] = 'scope use '.$alias.' = '.$use;
    }

    foreach (peek($scope, 'constants') ?? [] as $name => $type) {
        $lines[] = 'scope constant '.$name.' '.(is_object($type) ? typeId($type) : json_encode($type));
    }

    foreach (peek($scope, 'cases') ?? [] as $name => $case) {
        $lines[] = 'scope case '.$name.' '.(is_object($case) ? typeId($case) : json_encode($case));
    }

    foreach (peek($scope, 'parameters') ?? [] as $name => $type) {
        $lines[] = 'scope parameter '.$name.' '.typeId($type);
    }

    foreach (peek($scope, 'returnTypes') ?? [] as $index => $type) {
        $lines[] = 'scope return '.$index.' '.(is_object($type) ? typeId($type) : json_encode($type));
    }

    foreach (peek($scope, 'macros') ?? [] as $class => $macros) {
        foreach ($macros as $name => $type) {
            $lines[] = 'scope macro '.$class.'::'.$name.' '.typeId($type);
        }
    }

    foreach (peek($scope, 'validationRules') ?? [] as $key => $rules) {
        $lines[] = 'scope rule '.$key.' = '.json_encode($rules);
    }

    foreach (peek($scope, 'templateTags') ?? [] as $index => $tag) {
        $lines[] = 'scope template '.$index.' '.(is_object($tag) && $tag instanceof TypeContract ? typeId($tag) : get_debug_type($tag));
    }

    $receiver = peek($scope, 'receiverType');

    if ($receiver !== null) {
        $lines[] = 'scope receiver '.typeId($receiver);
    }

    return $lines;
}

function fieldLines(Scope $scope): array
{
    $lines = [];

    foreach ((new ReflectionObject($scope))->getProperties() as $property) {
        if (! $property->isInitialized($scope)) {
            $lines[] = 'field '.$property->getName().' <uninitialized>';

            continue;
        }

        $value = $property->getValue($scope);

        $lines[] = 'field '.$property->getName().' '.md5(serialize($value));
    }

    return $lines;
}

function surfaceLines(Scope $scope): array
{
    $result = $scope->result();

    if ($result === null) {
        return [...scopeLines($scope), '(no result)'];
    }

    if ($result instanceof MethodResult) {
        return [...scopeLines($scope), ...methodLines('method '.$result->name(), $result)];
    }

    /** @var ClassLikeResult $result */
    $lines = [
        ...scopeLines($scope),
        'class '.$result->name(),
        'namespace '.(peek($result, 'namespace') ?? '-'),
        'entity '.$result->entityType()->value,
        'extends '.implode(',', $result->extends()),
        'implements '.implode(',', $result->implements()),
        'traits '.implode(',', peek($result, 'traits') ?? []),
        'arrayable '.var_export($result->isArrayable(), true),
    ];

    foreach (peek($result, 'methods') ?? [] as $name => $method) {
        $lines = [...$lines, ...methodLines('method '.$name, $method)];
    }

    foreach (peek($result, 'properties') ?? [] as $name => $property) {
        $lines[] = 'property '.$name.' '.$property->visibility.' '.typeId($property->type)
            .' doc='.var_export($property->fromDocBlock, true)
            .' attr='.var_export($property->modelAttribute, true)
            .' rel='.var_export($property->modelRelation, true)
            .' ro='.var_export($property->readOnly, true)
            .' wo='.var_export($property->writeOnly, true);
    }

    foreach (peek($result, 'constants') ?? [] as $name => $constant) {
        $lines[] = 'constant '.$name.' '.typeId($constant->type);
    }

    sort($lines);

    return $lines;
}

// Ranger keeps its structure index alongside the analysis entries.
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
    $lines = $fields ? fieldLines($scope) : surfaceLines($scope);

    $rows[$path] = [
        'surface' => md5(implode("\n", $lines)),
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
