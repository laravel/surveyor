<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Coalesce extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Coalesce $node)
    {
        return Type::union(
            $this->from($node->left),
            $this->from($node->right),
        );
    }

    public function resolveForCondition(Node\Expr\BinaryOp\Coalesce $node)
    {
        return $this->resolve($node);
    }
}
