<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\Analysis\Condition;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Variable extends AbstractResolver
{
    public function resolve(Node\Expr\Variable $node)
    {
        return $this->scope->variables()->getAtLine($node->name, $node->getStartLine())['type'] ?? Type::mixed();
    }

    public function resolveForCondition(Node\Expr\Variable $node)
    {
        $type = $this->resolve($node);

        return new Condition($node->name, $type, $node->getStartLine());
    }
}
