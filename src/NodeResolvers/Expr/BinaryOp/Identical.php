<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Identical extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Identical $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\BinaryOp\Identical $node)
    {
        $var = match (true) {
            $node->left instanceof Node\Expr\Variable => $node->left,
            $node->right instanceof Node\Expr\Variable => $node->right,
            default => null,
        };

        if ($var === null) {
            return;
        }
    }
}
