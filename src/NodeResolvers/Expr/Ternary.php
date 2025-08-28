<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Ternary extends AbstractResolver
{
    public function resolve(Node\Expr\Ternary $node)
    {
        return Type::union(
            $this->from($node->if),
            $this->from($node->else),
        );
    }
}
