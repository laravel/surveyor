<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;
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
            Debug::ddAndOpen($type, $node, 'empty assessment is not a condition');
        }

        return $type
            ->whenTrue(fn ($_, TypeContract $type) => $type->nullable(true))
            ->whenFalse(fn ($_, TypeContract $type) => $type->nullable(false))
            ->makeTrue();
    }
}
