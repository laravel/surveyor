<?php

namespace Laravel\Surveyor\Types;

use Illuminate\Support\Collection;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Support\Util;
use Laravel\Surveyor\Types\Contracts\CollapsibleType;
use Throwable;

class Type
{
    public static function mixed(): Contracts\Type
    {
        return new MixedType;
    }

    public static function array($value): Contracts\Type
    {
        return new ArrayType($value);
    }

    public static function collapse(Contracts\Type $type): Contracts\Type
    {
        if ($type instanceof CollapsibleType) {
            return $type->collapse();
        }

        return $type;
    }

    public static function is(Contracts\Type $type, string|Contracts\Type ...$classes): bool
    {
        foreach ($classes as $class) {
            if ($class instanceof Contracts\Type) {
                if ($type instanceof $class) {
                    return true;
                }
            } elseif ($type instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public static function string(?string $value = null): Contracts\Type
    {
        try {
            if ($value !== null && Util::isClassOrInterface($value)) {
                return new ClassType($value);
            }
        } catch (Throwable $e) {
            return new StringType($value);
        }

        return new StringType($value);
    }

    public static function isSame(Contracts\Type $type1, Contracts\Type $type2): bool
    {
        return $type1->toString() === $type2->toString();
    }

    public static function int(?int $value = null): Contracts\Type
    {
        return new IntType($value);
    }

    public static function object(): Contracts\Type
    {
        return new ObjectType;
    }

    public static function number(int|float|null $value = null): Contracts\Type
    {
        return new NumberType($value);
    }

    public static function bool(?bool $bool = null): Contracts\Type
    {
        return new BoolType($bool);
    }

    public static function callable(array $parameters, ?Contracts\Type $returnType = null): Contracts\Type
    {
        return new CallableType($parameters, $returnType);
    }

    public static function arrayShape(Contracts\Type $keyType, Contracts\Type $itemType): Contracts\Type
    {
        return new ArrayShapeType($keyType, $itemType);
    }

    public static function null(): Contracts\Type
    {
        return new NullType;
    }

    public static function never(): Contracts\Type
    {
        return new NeverType;
    }

    public static function float(): Contracts\Type
    {
        return new FloatType;
    }

    public static function void(): Contracts\Type
    {
        return new VoidType;
    }

    public static function from(mixed $value): Contracts\Type
    {
        if ($value instanceof Contracts\Type) {
            return $value;
        }

        if ($value === null) {
            return self::null();
        }

        if (is_int($value)) {
            return self::int($value);
        }

        if (is_float($value)) {
            return self::float($value);
        }

        if (is_bool($value)) {
            return self::bool($value);
        }

        if ($value === 'callable') {
            return self::callable([]);
        }

        if (is_array($value)) {
            return self::array($value);
        }

        if (is_string($value)) {
            $result = match ($value) {
                'array' => self::array([]),
                'true' => self::bool(true),
                'false' => self::bool(false),
                'object', 'iterable' => self::arrayShape(self::mixed(), self::mixed()),
                'void' => self::void(),
                'mixed' => self::mixed(),
                'float' => self::float(),
                'int' => self::int(),
                'string' => self::string(),
                'bool' => self::bool(),
                'null' => self::null(),
                'callable' => self::callable([]),
                default => null,
            };

            if ($result) {
                return $result;
            }

            if (method_exists(self::class, $value) && $value !== 'from') {
                return self::$value();
            }

            return self::string($value);
        }

        Debug::ddAndOpen(Debug::trace(), $value, 'something else from');

        return self::string($value);
    }

    protected static function flattenUnion(array $args): Collection
    {
        return collect($args)->flatMap(
            fn ($type) => ($type instanceof UnionType)
                ? self::flattenUnion($type->types)
                : [$type]
        );
    }

    public static function union(...$args): Contracts\Type
    {
        $args = self::flattenUnion($args)
            ->filter()
            ->unique(fn ($type) => is_string($type) ? dd($type) : $type->toString())
            ->values();

        $nullType = $args->filter(fn ($type) => $type instanceof NullType);

        if ($nullType->isNotEmpty()) {
            $args = $args->map(fn ($type) => $type instanceof NullType ? null : $type->nullable())->filter()->values();
        }

        // Remove types that have a more specific counterpart
        $args = $args->filter(fn ($type) => ! $args->contains(
            fn ($otherType) => $type !== $otherType && $otherType->isMoreSpecificThan($type)
        ))->values();

        $args = $args->filter(fn ($type) => ! $type instanceof MixedType)->values();

        return match ($args->count()) {
            0 => Type::mixed(),
            1 => $args->first(),
            default => new UnionType($args->all()),
        };
    }

    public static function intersection(...$args): Contracts\Type
    {
        if (count($args) === 1) {
            return $args[0];
        }

        return new IntersectionType($args);
    }
}
