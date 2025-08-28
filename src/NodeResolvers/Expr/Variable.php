<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Variable extends AbstractResolver
{
    public function resolve(Node\Expr\Variable $node)
    {
        return $this->scope->stateTracker()->getVariableAtLine($node->name, $node->getStartLine())['type'];
    }
}
