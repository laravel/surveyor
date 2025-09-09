<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\Analysis\Condition;
use Laravel\StaticAnalyzer\Debug\Debug;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class BooleanNot extends AbstractResolver
{
    public function resolve(Node\Expr\BooleanNot $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\BooleanNot $node)
    {
        $type = $this->from($node->expr);

        if (! $type instanceof Condition) {
            Debug::ddFromClass($type, $node, 'boolean not is not a condition');
        }

        return $type->makeFalse();
    }
}
