<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class FuncCall extends AbstractResolver
{
    public function resolve(Node\Expr\FuncCall $node)
    {
        $returnTypes = [];

        if ($node->name instanceof Node\Expr\Variable) {
            $type = $this->scope->variables()->getAtLine($node->name->name, $node)['type'];

            if (! $type instanceof Types\CallableType) {
                Debug::ddAndOpen($type, $node, 'non-callable variable for func call');
            }

            return $type->returnType;
        }

        $name = $node->name->toString();

        $returnTypes = $this->reflector->functionReturnType($name, $node);

        return Type::union(...$returnTypes);
    }

    public function resolveForCondition(Node\Expr\FuncCall $node)
    {
        if ($node->name instanceof Node\Expr\Variable) {
            return null;
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

        $variableName = match (true) {
            $arg instanceof Node\Expr\Variable => $arg->name,
            $arg instanceof Node\Expr\PropertyFetch => $arg->name->name,
            $arg instanceof Node\Expr\StaticPropertyFetch => $arg->name->name,
            default => null,
        };

        if ($variableName === null) {
            return;
        }

        $condition = new Condition(
            $variableName,
            $this->scope->variables()->getAtLine($variableName, $node)['type'],
        );

        return $condition
            ->whenTrue(fn (Condition $c) => $c->setType($type))
            ->whenFalse(fn (Condition $c) => $c->removeType($type))
            ->makeTrue();
    }
}
