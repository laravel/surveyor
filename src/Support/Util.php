<?php

namespace Laravel\Surveyor\Support;

use Illuminate\Support\Facades\Facade;
use Laravel\Surveyor\Analysis\Scope;
use ReflectionClass;
use ReflectionException;

class Util
{
    protected static array $resolvedClasses = [];

    protected static array $isClassOrInterface = [];

    public static function isClassOrInterface(string $value): bool
    {
        return self::$isClassOrInterface[$value] ??= self::determineIsClassOrInterface($value);
    }

    protected static function determineIsClassOrInterface(string $value): bool
    {
        // Anything already loaded can be checked without the autoloader.
        if (self::isDeclared($value, false)) {
            return self::isSpelledLikeItsDeclaration($value);
        }

        // Asking class_exists() to autoload a name that Composer already
        // pulled in as a namespaced function through "autoload.files" fatals
        // with "cannot redeclare function". An unqualified name cannot reach
        // that file, and bailing out on one would lose the facade aliases,
        // whose names double as helper functions.
        if (str_contains($value, '\\') && (function_exists($value) || defined($value))) {
            return false;
        }

        return self::isDeclared($value, true) && self::isSpelledLikeItsDeclaration($value);
    }

    protected static function isDeclared(string $value, bool $autoload): bool
    {
        return class_exists($value, $autoload)
            || interface_exists($value, $autoload)
            || trait_exists($value, $autoload)
            || enum_exists($value, $autoload);
    }

    /**
     * PHP matches class names without regard to case, so the plain string
     * 'request' finds the Request facade alias and every literal spelled like
     * a class name becomes one.
     */
    protected static function isSpelledLikeItsDeclaration(string $value): bool
    {
        try {
            $declared = (new ReflectionClass($value))->getName();
        } catch (ReflectionException) {
            return false;
        }

        $parts = explode('\\', $declared);
        $short = end($parts);

        if ($value === $declared || $value === $short) {
            return true;
        }

        // An alias names a class that is genuinely spelled differently, so it
        // is still a class reference. A name that matches only once case is
        // ignored is not: that is an ordinary string colliding with a class.
        return strcasecmp($value, $declared) !== 0 && strcasecmp($value, $short) !== 0;
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
