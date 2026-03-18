<?php

namespace Laravel\Surveyor\Support;

use Illuminate\Support\Facades\Facade;
use Laravel\Surveyor\Analysis\Scope;
use ReflectionClass;

class Util
{
    protected static array $resolvedClasses = [];

    protected static array $isClassOrInterface = [];

    /**
     * PHP built-in constants that should never be treated as class/interface names.
     */
    protected static array $builtInConstants = [
        'null', 'true', 'false', 'inf', 'nan',
        'php_int_max', 'php_int_min', 'php_int_size',
        'php_float_max', 'php_float_min', 'php_float_dig', 'php_float_epsilon', 'php_float_inf', 'php_float_nan',
        'php_eol', 'php_maxpathlen', 'php_os', 'php_os_family',
        'php_sapi', 'php_major_version', 'php_minor_version', 'php_release_version', 'php_version', 'php_version_id',
        'directory_separator', 'path_separator',
        'stdin', 'stdout', 'stderr',
    ];

    public static function isClassOrInterface(string $value): bool
    {
        // Check function_exists() and defined() before class_exists() to prevent
        // the class autoloader from being triggered for namespaced functions that
        // were already loaded via Composer's "autoload.files", which would cause
        // a fatal "cannot redeclare function" error.
        //
        // We must exclude PHP built-in constants (NULL, TRUE, FALSE, INF, etc.)
        // from the defined() check, as they are not classes and would cause
        // ReflectionClass to throw when used downstream.
        return self::$isClassOrInterface[$value] ??= function_exists($value)
            || (defined($value) && ! in_array(strtolower($value), self::$builtInConstants, true))
            || class_exists($value)
            || interface_exists($value)
            || trait_exists($value)
            || enum_exists($value);
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

        try {
            $reflection = new ReflectionClass($value);
        } catch (\ReflectionException) {
            return $value;
        }

        if ($reflection->isSubclassOf(Facade::class)) {
            return ltrim(get_class($value::getFacadeRoot()), '\\');
        }

        return $value;
    }
}
