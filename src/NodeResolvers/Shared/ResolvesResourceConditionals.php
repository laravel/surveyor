<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use Throwable;

trait ResolvesResourceConditionals
{
    use ResolvesClosureReturnTypes;

    protected static array $conditionalMethods = [
        'when',
        'whenHas',
        'whenNotNull',
        'whenLoaded',
        'whenCounted',
        'whenAggregated',
        'whenPivotLoaded',
        'whenPivotLoadedAs',
        'mergeWhen',
    ];

    protected function isJsonResource(ClassType $var): bool
    {
        try {
            $className = $this->scope->getUse($var->value) ?? $var->value;

            return class_exists($className) && is_subclass_of($className, JsonResource::class);
        } catch (Throwable $e) {
            return false;
        }
    }

    protected function resolveResourceConditional(ClassType $var, string $method, Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node): TypeContract
    {
        $args = $node->getArgs();

        $type = match ($method) {
            'when' => $this->resolveWhen($args),
            'whenHas' => $this->resolveWhenHas($args),
            'whenNotNull' => $this->resolveWhenNotNull($args),
            'whenLoaded' => $this->resolveWhenLoaded($args),
            'whenCounted' => Type::int(),
            'whenAggregated' => Type::mixed(),
            'whenPivotLoaded', 'whenPivotLoadedAs' => $this->resolveWhenPivotLoaded($args),
            'mergeWhen' => $this->resolveMergeWhen($args),
            default => Type::mixed(),
        };

        return $type->optional();
    }

    /**
     * when($condition, $value, $default = null)
     * The value can be a literal or a closure.
     */
    protected function resolveWhen(array $args): TypeContract
    {
        if (! isset($args[1])) {
            return Type::mixed();
        }

        $valueExpr = $args[1]->value;

        // Check if it's a closure
        $closureType = $this->resolveClosureReturnType($valueExpr);
        if ($closureType) {
            return $closureType;
        }

        return $this->from($valueExpr) ?? Type::mixed();
    }

    /**
     * whenHas($attribute)
     * Returns the model attribute value if present.
     */
    protected function resolveWhenHas(array $args): TypeContract
    {
        if (! isset($args[0])) {
            return Type::mixed();
        }

        $attrName = $this->from($args[0]->value);

        if ($attrName instanceof \Laravel\Surveyor\Types\StringType && $attrName->value !== null) {
            $property = $this->scope->state()->properties()->get($attrName->value);

            if ($property) {
                return $property->type();
            }
        }

        return Type::mixed();
    }

    /**
     * whenNotNull($value)
     */
    protected function resolveWhenNotNull(array $args): TypeContract
    {
        if (! isset($args[0])) {
            return Type::mixed();
        }

        return $this->from($args[0]->value) ?? Type::mixed();
    }

    /**
     * whenLoaded($relation)
     * Returns the relation value if eager-loaded.
     */
    protected function resolveWhenLoaded(array $args): TypeContract
    {
        if (! isset($args[0])) {
            return Type::mixed();
        }

        // If there's a second argument, it's the value to use (closure or value)
        if (isset($args[1])) {
            $closureType = $this->resolveClosureReturnType($args[1]->value);
            if ($closureType) {
                return $closureType;
            }

            return $this->from($args[1]->value) ?? Type::mixed();
        }

        // Otherwise, look up the relation type from model properties
        $relationName = $this->from($args[0]->value);

        if ($relationName instanceof \Laravel\Surveyor\Types\StringType && $relationName->value !== null) {
            $property = $this->scope->state()->properties()->get($relationName->value);

            if ($property) {
                return $property->type();
            }
        }

        return Type::mixed();
    }

    /**
     * whenPivotLoaded($table, $value) / whenPivotLoadedAs($accessor, $table, $value)
     */
    protected function resolveWhenPivotLoaded(array $args): TypeContract
    {
        // The last argument is the value (closure or literal)
        $valueArg = end($args);

        if (! $valueArg) {
            return Type::mixed();
        }

        $closureType = $this->resolveClosureReturnType($valueArg->value);
        if ($closureType) {
            return $closureType;
        }

        return $this->from($valueArg->value) ?? Type::mixed();
    }

    /**
     * mergeWhen($condition, $array)
     * Each key in $array becomes optional in the parent.
     */
    protected function resolveMergeWhen(array $args): TypeContract
    {
        if (! isset($args[1])) {
            return Type::mixed();
        }

        $valueExpr = $args[1]->value;

        // Check if it's a closure
        $closureType = $this->resolveClosureReturnType($valueExpr);
        if ($closureType) {
            return $closureType;
        }

        $resolved = $this->from($valueExpr);

        if ($resolved instanceof \Laravel\Surveyor\Types\ArrayType) {
            // Mark each value in the array as optional
            $optionalValues = [];
            foreach ($resolved->value as $key => $value) {
                if ($value instanceof TypeContract) {
                    $optionalValues[$key] = $value->optional();
                } else {
                    $optionalValues[$key] = $value;
                }
            }

            return new \Laravel\Surveyor\Types\ArrayType($optionalValues);
        }

        return $resolved ?? Type::mixed();
    }
}
