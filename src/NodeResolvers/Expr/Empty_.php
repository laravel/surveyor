<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\Analysis\Condition;
use Laravel\StaticAnalyzer\Debug\Debug;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Contracts\Type as TypeContract;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Empty_ extends AbstractResolver
{
    public function resolve(Node\Expr\Empty_ $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\Empty_ $node)
    {
        $type = $this->from($node->expr);

        if (! $type instanceof Condition) {
            Debug::ddFromClass($type, $node, 'empty assessment is not a condition');
        }

        return $type
            ->whenTrue(fn (TypeContract $type) => $type->nullable(true))
            ->whenFalse(fn (TypeContract $type) => $type->nullable(false))
            ->makeTrue();
    }
}
