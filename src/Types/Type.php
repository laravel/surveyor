<?php

namespace Laravel\Surveyor\Types;

use Laravel\Surveyor\Result\VariableState;
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
        return new ArrayType(array_map(
            fn ($v) => $v instanceof VariableState ? $v->type() : $v,
            $value,
        ));
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

    public static function number(): Contracts\Type
    {
        return new NumberType;
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

    public static function float(?float $value = null): Contracts\Type
    {
        return new FloatType($value);
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
                'int', 'integer' => self::int(),
                'string' => self::string(),
                'bool' => self::bool(),
                'null' => self::null(),
                default => null,
            };

            if ($result) {
                return $result;
            }

            return self::string($value);
        }

        if (! is_string($value)) {
            return self::mixed();
        }

        return self::string($value);
    }

    /**
     * Flatten nested unions into $types, dropping empty members and unwrapping
     * variable states.
     *
     * @param  list<Contracts\Type|VariableState|null>  $args
     * @param  list<Contracts\Type>  $types
     */
    protected static function flattenUnion(array $args, array &$types): void
    {
        foreach ($args as $type) {
            if ($type instanceof UnionType) {
                self::flattenUnion($type->types, $types);

                continue;
            }

            if (! $type) {
                continue;
            }

            $types[] = $type instanceof VariableState ? $type->type() : $type;
        }
    }

    public static function union(...$args): Contracts\Type
    {
        $types = [];

        self::flattenUnion($args, $types);

        if ($types === []) {
            return self::mixed();
        }

        // With one member there is nothing to de-duplicate or compare, so skip
        // toString(), which is a json_encode() for array and union types.
        if (count($types) === 1) {
            return ($types[0] instanceof MixedType || $types[0] instanceof NullType)
                ? self::mixed()
                : $types[0];
        }

        $unique = [];
        $seen = [];
        $hasNull = false;

        foreach ($types as $type) {
            $key = $type->toString();

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $type;
            $hasNull = $hasNull || $type instanceof NullType;
        }

        // Null is carried by marking the other members nullable.
        if ($hasNull) {
            $nullable = [];

            foreach ($unique as $type) {
                if (! $type instanceof NullType) {
                    $nullable[] = $type->nullable();
                }
            }

            $unique = $nullable;
        }

        $remaining = [];

        foreach ($unique as $type) {
            if ($type instanceof MixedType) {
                continue;
            }

            // Remove types that have a more specific counterpart.
            foreach ($unique as $other) {
                if ($type !== $other && $other->isMoreSpecificThan($type)) {
                    continue 2;
                }
            }

            $remaining[] = $type;
        }

        return match (count($remaining)) {
            0 => self::mixed(),
            1 => $remaining[0],
            default => new UnionType($remaining),
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
