<?php

namespace Laravel\Surveyor\Support;

use Illuminate\Support\Facades\Facade;
use Laravel\Surveyor\Analysis\Scope;
use ReflectionClass;

class Util
{
    protected static array $resolvedClasses = [];

    protected static array $isClassOrInterface = [];

    public static function isClassOrInterface(string $value): bool
    {
        return self::$isClassOrInterface[$value] ??= class_exists($value)
            || interface_exists($value)
            || trait_exists($value)
            || enum_exists($value)
            || function_exists($value)
            || defined($value);
    }

    public static function resolveValidClass(string $value, Scope $scope): string
    {
        $value = $scope->getUse($value);

        if (! self::isClassOrInterface($value) && str_contains($value, '\\')) {
            // Try again from the base of the name, weird bug in the parser
            $parts = explode('\\', $value);
            $end = array_pop($parts);
            $value = $scope->getUse($end);
        }

        return $value;
    }

    public static function resolveClass(string $value): string
    {
        return self::$resolvedClasses[$value] ??= self::resolveClassInternal($value);
    }

    protected static function resolveClassInternal(string $value): string
    {
        // Only attempt Reflection on actual classes/interfaces/traits/enums.
        // Do not treat functions or defined constants (e.g. `true`, `false`) as classes.
        if (! (class_exists($value) || interface_exists($value) || trait_exists($value) || enum_exists($value))) {
            return $value;
        }

        $reflection = new ReflectionClass($value);

        if ($reflection->isSubclassOf(Facade::class)) {
            return ltrim(get_class($value::getFacadeRoot()), '\\');
        }

        return $value;
    }
}
