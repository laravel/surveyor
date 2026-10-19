<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types;
use Laravel\Surveyor\Types\Entities\InertiaRender;
use Laravel\Surveyor\Types\Entities\JsonEncoded;
use Laravel\Surveyor\Types\Entities\View;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use ReflectionMethod;

class FuncCall extends AbstractResolver
{
    public function resolve(Node\Expr\FuncCall $node)
    {
        $returnTypes = [];

        if ($this->scope->state()->canHandle($node->name)) {
            $type = $this->scope->state()->getAtLine($node->name)?->type();

            if ($type === null || ! $type instanceof Types\CallableType) {
                return Type::mixed();
            }

            return $type->returnType;
        }

        if ($node->name instanceof Node\Expr\ArrayDimFetch) {
            return $this->from($node->name);
        }

        if (! $node->name instanceof Node\Name) {
            return Type::mixed();
        }

        $name = $node->name->toString();

        if ($name === 'json_encode' && ! $node->isFirstClassCallable() && isset($node->args[0])) {
            return $this->resolveJsonEncode($node);
        }

        $returnTypes = array_merge(
            $this->reflector->functionReturnType($name, $node),
            $this->handleEntities($name, $node),
        );

        return Type::union(...$returnTypes);
    }

    /**
     * Keep the encoded value's type so a toJson() built on json_encode() can be
     * read back. Flags that change the structure are honored or, when they
     * cannot be read, leave the payload unknown.
     */
    protected function resolveJsonEncode(Node\Expr\FuncCall $node): Types\Contracts\Type
    {
        $arguments = [];

        foreach ($node->getArgs() as $index => $argument) {
            $arguments[$argument->name?->name ?? ['value', 'flags', 'depth'][$index]] = $argument->value;
        }

        $data = isset($arguments['value']) ? $this->from($arguments['value']) : Type::mixed();
        $flags = isset($arguments['flags']) ? $this->jsonFlags($arguments['flags']) : 0;

        if ($flags === null || ($flags & JSON_NUMERIC_CHECK)) {
            $data = Type::mixed();
        } elseif ($flags & JSON_FORCE_OBJECT) {
            $data = $this->forceJsonObject($data);
        }

        $encoded = new JsonEncoded($data);

        return $flags !== null && ($flags & JSON_THROW_ON_ERROR)
            ? $encoded
            : Type::union($encoded, Type::bool(false));
    }

    /**
     * The json_encode() flags an expression evaluates to, or null when unknown.
     */
    protected function jsonFlags(Node\Expr $expression): ?int
    {
        if ($expression instanceof Node\Expr\ConstFetch && str_starts_with($expression->name->toString(), 'JSON_')) {
            $constant = $expression->name->toString();

            return defined($constant) ? constant($constant) : null;
        }

        if ($expression instanceof Node\Expr\BinaryOp\BitwiseOr) {
            $left = $this->jsonFlags($expression->left);
            $right = $this->jsonFlags($expression->right);

            return $left === null || $right === null ? null : $left | $right;
        }

        $type = $this->from($expression);

        if ($type instanceof Types\IntType && $type->value !== null) {
            return $type->value;
        }

        // Laravel serializes Jsonable values by calling toJson() without arguments.
        if ($expression instanceof Node\Expr\Variable && $this->scope->methodName() === 'toJson') {
            $method = new ReflectionMethod($this->scope->entityName(), 'toJson');

            foreach ($method->getParameters() as $parameter) {
                if ($parameter->getName() === $expression->name && $parameter->isDefaultValueAvailable()) {
                    $default = $parameter->getDefaultValue();

                    return is_int($default) ? $default : null;
                }
            }
        }

        return null;
    }

    /**
     * JSON_FORCE_OBJECT encodes every list as an object keyed by index.
     */
    protected function forceJsonObject(Types\Contracts\Type $data): Types\Contracts\Type
    {
        if ($data instanceof Types\ArrayType) {
            $values = array_map(fn ($value) => $this->forceJsonObject($value), $data->value);

            return $data->isList()
                ? Type::arrayShape(Type::string(), Type::union(...$values))
                : Type::array($values);
        }

        if ($data instanceof Types\ArrayShapeType) {
            return Type::arrayShape(Type::string(), $this->forceJsonObject($data->valueType));
        }

        return $data;
    }

    protected function handleEntities(string $name, Node\Expr\FuncCall $node): array
    {
        return match ($name) {
            'view' => $this->handleViewEntity($node),
            'inertia' => $this->handleInertiaEntity($node),
            default => [],
        };
    }

    protected function handleViewEntity(Node\Expr\FuncCall $node): array
    {
        $args = array_map(fn ($arg) => $this->from($arg->value), $node->getArgs());

        if (count($args) === 0) {
            return [];
        }

        return [
            new View(
                $args[0]->value,
                $args[1] ?? Type::arrayShape(Type::string(), Type::mixed()),
            ),
        ];
    }

    protected function handleInertiaEntity(Node\Expr\FuncCall $node): array
    {
        $args = array_map(fn ($arg) => $this->from($arg->value), $node->getArgs());

        if (count($args) === 0) {
            return [];
        }

        return [
            new InertiaRender(
                $args[0]->value,
                $args[1] ?? Type::arrayShape(Type::string(), Type::mixed()),
            ),
        ];
    }

    public function resolveForCondition(Node\Expr\FuncCall $node)
    {
        if (! $node->name instanceof Node\Name) {
            return Type::mixed();
        }

        $type = match ($node->name->toString()) {
            'is_array' => new Types\ArrayType([]),
            'is_bool' => new Types\BoolType,
            'is_int' => new Types\IntType,
            'is_integer' => new Types\IntType,
            'is_null' => new Types\NullType,
            'is_numeric' => new Types\NumberType,
            'is_string' => new Types\StringType,
            'is_callable' => new Types\CallableType([]),
            // 'is_double' => Types\DoubleType::class,
            // 'is_float' => Types\FloatType::class,
            // 'is_long' => Types\LongType::class,
            // 'is_object' => Types\ObjectType::class,
            // 'is_resource' => Types\ResourceType::class,
            default => null,
        };

        if ($type === null) {
            return;
        }

        $arg = $node->args[0]->value;

        if ($this->scope->state()->canHandle($arg)) {
            return Condition::from(
                $arg,
                $this->scope->state()->getAtLine($arg)?->type() ?? Type::mixed(),
            )
                ->whenTrue(fn (Condition $c) => $c->setType($type))
                ->whenFalse(fn (Condition $c) => $c->removeType($type))
                ->makeTrue();
        }

        if ($arg instanceof Node\Expr\Assign) {
            return $this->from($arg);
        }

        return null;
    }
}
