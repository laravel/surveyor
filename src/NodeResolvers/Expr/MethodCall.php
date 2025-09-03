<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class MethodCall extends AbstractResolver
{
    public function resolve(Node\Expr\MethodCall $node)
    {
        try {
            return Type::union(
                ...$this->reflector->methodReturnType($this->from($node->var), $node->name, $node)
            );
        } catch (\Throwable $e) {
            dd($node, $this->from($node->var), $e->getMessage());
        }
    }
}
