<?php

namespace Laravel\Surveyor\Concerns;

use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;

trait SubstitutesTemplateBindings
{
    protected function substituteInType(TypeContract $type, array $bindings): TypeContract
    {
        return match (true) {
            $type instanceof StringType && isset($bindings[$type->value]) => $bindings[$type->value],
            $type instanceof ArrayShapeType => new ArrayShapeType(
                $this->substituteInType($type->keyType, $bindings),
                $this->substituteInType($type->valueType, $bindings),
            ),
            $type instanceof ArrayType => $this->substituteInArrayType($type, $bindings),
            $type instanceof UnionType => Type::union(...array_map(fn ($t) => $this->substituteInType($t, $bindings), $type->types)),
            $type instanceof ClassType && ! empty($type->genericTypes()) => (clone $type)->setGenericTypes(
                array_map(fn ($g) => $this->substituteInType($g, $bindings), $type->genericTypes())
            ),
            default => $type,
        };
    }

    protected function substituteInArrayType(ArrayType $type, array $bindings): ArrayType
    {
        $newValues = [];
        foreach ($type->value as $key => $value) {
            $newValues[$key] = $this->substituteInType($value, $bindings);
        }

        return new ArrayType($newValues);
    }
}
