<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\Analysis\Condition;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types;
use Laravel\StaticAnalyzer\Types\Contracts\Type as TypeContract;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class FuncCall extends AbstractResolver
{
    public function resolve(Node\Expr\FuncCall $node)
    {
        $returnTypes = [];

        $name = $node->name->toString();

        $returnTypes = $this->reflector->functionReturnType($name, $node);

        return Type::union(...$returnTypes);
    }

    public function resolveForCondition(Node\Expr\FuncCall $node)
    {
        $type = match ($node->name->toString()) {
            'is_array' => new Types\ArrayType([]),
            'is_bool' => new Types\BoolType,
            'is_int' => new Types\IntType,
            'is_integer' => new Types\IntType,
            'is_null' => new Types\NullType,
            'is_numeric' => new Types\NumberType,
            'is_string' => new Types\StringType,
            // 'is_callable' => Types\CallableType::class,
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
            $this->scope->variables()->getAtLine($variableName, $node->getStartLine())['type'],
            $node->getStartLine(),
        );

        return $condition
            ->whenTrue(fn (TypeContract $t) => $condition->setType($type))
            ->whenFalse(fn (TypeContract $t) => $condition->removeType($type))
            ->makeTrue();
    }
}
