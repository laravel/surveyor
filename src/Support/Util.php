<?php

namespace Laravel\Surveyor\Support;

use Illuminate\Support\Facades\Facade;
use ReflectionClass;

class Util
{
    protected static array $resolvedClasses = [];

    protected static array $isClassOrInterface = [];

    public static function isClassOrInterface(string $value): bool
    {
        return self::$isClassOrInterface[$value] ??= class_exists($value) || interface_exists($value);
    }

    public static function resolveClass(string $value): string
    {
        return self::$resolvedClasses[$value] ??= self::resolveClassInternal($value);
    }

    protected static function resolveClassInternal(string $value): string
    {
        if (! self::isClassOrInterface($value)) {
            // TODO: This *shouldn't* happen, but it does. Need to figure out why.
            return $value;
        }

        $reflection = new ReflectionClass($value);

        if ($reflection->isSubclassOf(Facade::class)) {
            return ltrim(get_class($value::getFacadeRoot()), '\\');
        }

        // if (app()->getBindings()[$value] ?? null) {
        //     return app()->getBindings()[$value]->getConcrete();
        // }

        return $value;
    }
}
