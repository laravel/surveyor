<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzed\MethodResult;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Throwable;

/**
 * Everything a dependent can read off an analyzed file, as text.
 *
 * A file's own analysis changes whenever its body does. What its dependents
 * can see is narrower: the classes it declares, their methods, what those
 * methods take and return, and the imports names are resolved against. Two
 * analyses that agree here cannot lead a dependent to a different answer, so
 * this is what a cache entry can be fingerprinted on.
 *
 * The state tracker is left out. It holds the variables of the run that
 * produced the entry, nothing reads it back, and it does not survive
 * re-analysis unchanged.
 */
class Surface
{
    public static function hash(Scope $scope): string
    {
        return hash('xxh128', implode("\n", static::lines($scope)));
    }

    /**
     * @return list<string>
     */
    public static function lines(Scope $scope): array
    {
        $lines = static::scopeLines($scope);
        $result = $scope->result();

        if ($result instanceof MethodResult) {
            $lines = [...$lines, ...static::methodLines('method '.$result->name(), $result)];
        } elseif ($result instanceof ClassLikeResult) {
            $lines = [...$lines, ...static::classLines($result)];
        } else {
            // Some files record nothing observable. An enum is the common case:
            // its cases are never analyzed, dependents read them off PHP's own
            // reflection, and two enums in one namespace look identical here.
            // With no surface to compare, the bytes stand in for one, so any
            // edit to such a file counts as a change to its surface.
            $lines[] = 'no result, bytes '.static::fileHash($scope->fullPath());
        }

        // Methods and properties are keyed by name, so sorting keeps the hash
        // independent of the order they were recorded in.
        sort($lines);

        return $lines;
    }

    /**
     * @return list<string>
     */
    protected static function scopeLines(Scope $scope): array
    {
        $lines = [
            'scope entity '.($scope->entityName() ?? '-'),
            'scope method '.($scope->methodName() ?? '-'),
            'scope namespace '.($scope->namespace() ?? '-'),
            'scope extends '.implode(',', $scope->extends()),
            'scope implements '.implode(',', $scope->implements()),
            'scope traits '.implode(',', $scope->traits()),
        ];

        foreach ($scope->uses() as $alias => $use) {
            $lines[] = 'scope use '.$alias.' = '.$use;
        }

        foreach ($scope->constants() as $name => $type) {
            $lines[] = 'scope constant '.$name.' '.static::describe($type);
        }

        foreach ($scope->parameters() as $name => $type) {
            $lines[] = 'scope parameter '.$name.' '.static::describe($type);
        }

        foreach ($scope->returnTypes() as $index => $type) {
            $lines[] = 'scope return '.$index.' '.static::describe($type);
        }

        foreach ($scope->macros() as $class => $macros) {
            foreach ($macros as $name => $type) {
                $lines[] = 'scope macro '.$class.'::'.$name.' '.static::describe($type);
            }
        }

        foreach ($scope->validationRules() as $key => $rules) {
            $lines[] = 'scope rule '.$key.' = '.json_encode($rules);
        }

        foreach ($scope->getTemplateTags() as $index => $tag) {
            $lines[] = 'scope template '.$index.' '.static::describe($tag);
        }

        if ($receiver = $scope->getReceiverType()) {
            $lines[] = 'scope receiver '.static::describe($receiver);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    protected static function classLines(ClassLikeResult $result): array
    {
        $lines = [
            'class '.$result->name(),
            'namespace '.($result->namespace() ?? '-'),
            'entity '.$result->entityType()->value,
            'extends '.implode(',', $result->extends()),
            'implements '.implode(',', $result->implements()),
            'arrayable '.var_export($result->isArrayable(), true),
        ];

        foreach ($result->publicMethods() as $name => $method) {
            $lines = [...$lines, ...static::methodLines('method '.$name, $method)];
        }

        foreach ($result->properties() as $name => $property) {
            $lines[] = 'property '.$name.' '.$property->visibility.' '.static::describe($property->type)
                .' doc='.var_export($property->fromDocBlock, true)
                .' attribute='.var_export($property->modelAttribute, true)
                .' relation='.var_export($property->modelRelation, true)
                .' readonly='.var_export($property->readOnly, true)
                .' writeonly='.var_export($property->writeOnly, true);
        }

        foreach ($result->constants() as $name => $constant) {
            $lines[] = 'constant '.$name.' '.static::describe($constant->type);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    protected static function methodLines(string $prefix, MethodResult $method): array
    {
        $parameters = [];

        foreach ($method->parameters() as $name => $type) {
            $parameters[] = $name.' '.static::describe($type);
        }

        $returns = [];

        foreach ($method->returnTypes() as $entry) {
            $returns[] = static::describe($entry['type']);
        }

        $lines = [
            $prefix.' ('.implode(', ', $parameters).') -> '.implode(' + ', $returns)
                .' relation='.var_export($method->isModelRelation(), true),
        ];

        foreach ($method->validationRules() as $key => $rules) {
            $lines[] = $prefix.' rule '.$key.' = '.json_encode($rules);
        }

        return $lines;
    }

    protected static function fileHash(?string $path): string
    {
        if ($path === null || ! is_file($path)) {
            return '-';
        }

        return hash_file('xxh128', $path);
    }

    protected static function describe(mixed $type): string
    {
        if (! $type instanceof TypeContract) {
            return $type === null ? '-' : get_debug_type($type);
        }

        try {
            $id = $type->id();
        } catch (Throwable) {
            // A type that cannot name itself still has to hash to the same
            // thing every time, so the class name stands in for its id.
            $id = 'unnameable';
        }

        return $type::class.'('.$id.')'
            .($type->isNullable() ? '|null' : '')
            .($type->isOptional() ? '|optional' : '');
    }
}
