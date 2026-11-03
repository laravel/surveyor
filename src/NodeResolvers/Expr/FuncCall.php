<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types;
use Laravel\Surveyor\Types\Entities\InertiaRender;
use Laravel\Surveyor\Types\Entities\View;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

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

        $returnTypes = array_merge(
            $this->reflector->functionReturnType($name, $node),
            $this->handleEntities($name, $node),
        );

        return Type::union(...$returnTypes);
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

        // A page type is only useful when the component name is known exactly.
        if (! (($args[0] ?? null) instanceof Types\StringType) || $args[0]->value === null) {
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
