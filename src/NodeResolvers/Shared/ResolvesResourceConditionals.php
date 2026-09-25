<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\ResourceMerge;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use Throwable;

trait ResolvesResourceConditionals
{
    use ResolvesClosureReturnTypes;

    protected static array $conditionalMethods = [
        'when',
        'unless',
        'whenHas',
        'whenNull',
        'whenNotNull',
        'whenAppended',
        'whenLoaded',
        'whenCounted',
        'whenAggregated',
        'whenExistsLoaded',
        'whenPivotLoaded',
        'whenPivotLoadedAs',
        'merge',
        'mergeWhen',
        'mergeUnless',
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

        return match ($method) {
            'when', 'unless' => $this->resolveWhen($args),
            'whenHas', 'whenAppended' => $this->resolveWhenHas($args),
            'whenNull' => $this->resolveWhenNull($args),
            'whenNotNull' => $this->resolveWhenNotNull($args),
            'whenLoaded' => $this->resolveWhenLoaded($args),
            'whenCounted' => $this->resolveWhenCounted($args),
            'whenAggregated' => $this->resolveWhenAggregated($args),
            'whenExistsLoaded' => $this->resolveWhenExistsLoaded($args),
            'whenPivotLoaded' => $this->resolveWhenPivotLoaded($args),
            'whenPivotLoadedAs' => $this->resolveWhenPivotLoadedAs($args),
            'merge' => $this->resolveMerge($args),
            'mergeWhen', 'mergeUnless' => $this->resolveMergeWhen($args),
            default => Type::mixed(),
        };
    }

    /**
     * when($condition, $value, $default = new MissingValue) / unless(...)
     * The value can be a literal or a closure.
     */
    protected function resolveWhen(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['condition', 'value', 'default']);
        $type = $this->resolveResourceValue($args['value'] ?? null);

        return $this->withResourceDefault($type, $args['default'] ?? null);
    }

    /**
     * whenHas($attribute, $value = null, $default = new MissingValue) / whenAppended(...)
     * Returns the model attribute value if present.
     */
    protected function resolveWhenHas(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['attribute', 'value', 'default']);

        if (isset($args['value'])) {
            $type = $this->resolveResourceValue($args['value']);
        } else {
            $type = $this->resolveResourceProperty($args['attribute'] ?? null);
        }

        return $this->withResourceDefault($type, $args['default'] ?? null);
    }

    /**
     * whenNull($value, $default = new MissingValue)
     */
    protected function resolveWhenNull(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['value', 'default']);

        // Still resolve the input for expressions that update the scope.
        $this->resolveResourceValue($args['value'] ?? null);

        return $this->withResourceDefault(Type::null(), $args['default'] ?? null);
    }

    /**
     * whenNotNull($value, $default = new MissingValue)
     */
    protected function resolveWhenNotNull(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['value', 'default']);
        $type = $this->resolveResourceValue($args['value'] ?? null);

        return $this->withResourceDefault((clone $type)->nullable(false), $args['default'] ?? null);
    }

    /**
     * whenLoaded($relationship, $value = null, $default = new MissingValue)
     * Returns the relation value if eager-loaded.
     */
    protected function resolveWhenLoaded(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['relationship', 'value', 'default']);

        // Look up the relation type from model properties
        $loaded = $this->resolveResourceProperty($args['relationship'] ?? null);

        return $this->withResourceDefault(
            $this->resolveLoadedValue($loaded, $args['value'] ?? null),
            $args['default'] ?? null,
        );
    }

    /**
     * whenCounted($relationship, $value = null, $default = new MissingValue)
     */
    protected function resolveWhenCounted(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['relationship', 'value', 'default']);

        return $this->withResourceDefault(
            $this->resolveLoadedValue(Type::int(), $args['value'] ?? null),
            $args['default'] ?? null,
        );
    }

    /**
     * whenAggregated($relationship, $column, $aggregate, $value = null, $default = new MissingValue)
     */
    protected function resolveWhenAggregated(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['relationship', 'column', 'aggregate', 'value', 'default']);

        // An aggregate over a relation with no rows is null.
        return $this->withResourceDefault(
            $this->resolveLoadedValue(Type::mixed(), $args['value'] ?? null, nullable: true),
            $args['default'] ?? null,
        );
    }

    /**
     * whenExistsLoaded($relationship, $value = null, $default = new MissingValue)
     */
    protected function resolveWhenExistsLoaded(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['relationship', 'value', 'default']);

        if (isset($args['value'])) {
            $type = $this->resolveResourceValue($args['value']);
        } else {
            $type = Type::bool();
        }

        return $this->withResourceDefault($type, $args['default'] ?? null);
    }

    /**
     * whenPivotLoaded($table, $value, $default = new MissingValue)
     */
    protected function resolveWhenPivotLoaded(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['table', 'value', 'default']);
        $type = $this->resolveResourceValue($args['value'] ?? null);

        return $this->withResourceDefault($type, $args['default'] ?? null);
    }

    /**
     * whenPivotLoadedAs($accessor, $table, $value, $default = new MissingValue)
     */
    protected function resolveWhenPivotLoadedAs(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['accessor', 'table', 'value', 'default']);
        $type = $this->resolveResourceValue($args['value'] ?? null);

        return $this->withResourceDefault($type, $args['default'] ?? null);
    }

    /**
     * merge($value)
     */
    protected function resolveMerge(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['value']);
        $type = $this->resolveResourceValue($args['value'] ?? null);

        return $this->resolveResourceMerge($type, default: null, optional: false);
    }

    /**
     * mergeWhen($condition, $value, $default = new MissingValue) / mergeUnless(...)
     * Each key in $value becomes optional in the parent unless the default has it too.
     */
    protected function resolveMergeWhen(array $args): TypeContract
    {
        $args = $this->resourceConditionalArguments($args, ['condition', 'value', 'default']);
        $type = $this->resolveResourceValue($args['value'] ?? null);
        $default = $this->resolveResourceDefault($args['default'] ?? null);

        return $this->resolveResourceMerge($type, $default, optional: $default === null);
    }

    /**
     * Given a value, whenLoaded(), whenCounted(), and whenAggregated() pass it
     * the loaded value, but return null without calling it when that value is
     * null. An explicit null value stands for the loaded value itself.
     */
    protected function resolveLoadedValue(TypeContract $loaded, ?Node\Expr $value, bool $nullable = false): TypeContract
    {
        if ($value === null || $this->isNullExpression($value)) {
            return $loaded;
        }

        $type = $this->resolveResourceValue($value);

        return $nullable || $loaded->isNullable() ? Type::union($type, Type::null()) : $type;
    }

    protected function isNullExpression(Node\Expr $expression): bool
    {
        return $expression instanceof Node\Expr\ConstFetch && strtolower($expression->name->toString()) === 'null';
    }

    /**
     * Map positional and named arguments to the helper's parameter names.
     *
     * @param  array<Node\Arg>  $args
     * @param  list<string>  $parameters
     * @return array<string, Node\Expr>
     */
    protected function resourceConditionalArguments(array $args, array $parameters): array
    {
        $arguments = [];

        foreach ($args as $index => $arg) {
            $name = $arg->name?->name ?? $parameters[$index];

            $arguments[$name] = $arg->value;
        }

        return $arguments;
    }

    protected function withResourceDefault(TypeContract $type, ?Node\Expr $expression): TypeContract
    {
        $default = $this->resolveResourceDefault($expression);

        if ($default !== null) {
            $type = Type::union($type, $default);
        }

        // Clone before mutating: $type may be a shared instance held by the scope's
        // property store and the model's PropertyResult (see resolveWhenHas / resolveWhenLoaded).
        return (clone $type)->required($default !== null);
    }

    protected function resolveResourceDefault(?Node\Expr $expression): ?TypeContract
    {
        if ($expression === null) {
            return null;
        }

        $type = $this->resolveResourceValue($expression);

        // An explicit null is a real default (a NullType); MissingValue is not.
        return $this->isMissingResourceValue($type) ? null : $type;
    }

    protected function resolveResourceValue(?Node\Expr $expression): TypeContract
    {
        if ($expression === null) {
            return Type::mixed();
        }

        $type = $this->resolveClosureReturnType($expression);

        if (! $type instanceof ArrayType || $type->value !== []) {
            return $type ?? $this->from($expression) ?? Type::mixed();
        }

        // An explicit `array` return type has no keys. Inspect the returned
        // expression as well so resource merges can retain the array's shape.
        if ($expression instanceof Node\Expr\ArrowFunction) {
            return $this->from($expression->expr) ?? $type;
        }

        if ($expression instanceof Node\Expr\Closure) {
            foreach ($expression->stmts as $statement) {
                if ($statement instanceof Node\Stmt\Return_ && $statement->expr !== null) {
                    return $this->from($statement->expr) ?? $type;
                }
            }
        }

        return $type;
    }

    protected function resolveResourceProperty(?Node\Expr $expression): TypeContract
    {
        $name = $expression === null ? null : $this->from($expression);

        if ($name instanceof StringType && $name->value !== null) {
            return $this->scope->state()->properties()->get($name->value) ?? Type::mixed();
        }

        return Type::mixed();
    }

    protected function isMissingResourceValue(TypeContract $type): bool
    {
        return $type instanceof ClassType && $type->resolved() === MissingValue::class;
    }

    /**
     * Describe the keys a merge adds to its containing array. A value whose
     * keys cannot be read adds none, rather than an entry under a numeric key.
     */
    protected function resolveResourceMerge(TypeContract $value, ?TypeContract $default, bool $optional): TypeContract
    {
        if (! $value instanceof ArrayType) {
            return new ResourceMerge([]);
        }

        $values = [];
        $defaultValues = $default instanceof ArrayType ? $default->value : [];
        $keys = array_unique([...array_keys($value->value), ...array_keys($defaultValues)]);

        foreach ($keys as $key) {
            $valueType = $value->value[$key] ?? null;
            $defaultType = $defaultValues[$key] ?? null;

            if ($valueType !== null && $defaultType !== null) {
                $type = Type::union($valueType, $defaultType);
            } else {
                $type = $valueType ?? $defaultType;
            }

            $keyIsOptional = $optional || $type->isOptional();

            // With a default branch, only keys present in both branches can be required.
            if ($default !== null && ($valueType === null || $defaultType === null)) {
                $keyIsOptional = true;
            }

            // Clone first — these may be shared instances from the scope's property store.
            $values[$key] = (clone $type)->required(! $keyIsOptional);
        }

        return new ResourceMerge($values);
    }
}
