<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\Debug\Debug;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\ClassType;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class MethodCall extends AbstractResolver
{
    public function resolve(Node\Expr\MethodCall $node)
    {
        $var = $this->from($node->var);

        if (! $var instanceof ClassType) {
            Debug::ddFromClass($var, $node, 'non-class for method call?');
        }

        return Type::union(
            ...$this->reflector->methodReturnType($this->scope->getUse($var->value), $node->name, $node)
        );
    }
}
