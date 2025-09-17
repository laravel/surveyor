<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class BooleanOr extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\BooleanOr $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\BinaryOp\BooleanOr $node)
    {
        $left = $this->from($node->left);
        $right = $this->from($node->right);

        // TODO: This seem incorrect
        if ($left instanceof Condition) {
            $this->scope->state()->narrow($left->node, $left->apply(), $node);
        }

        if ($right instanceof Condition) {
            $this->scope->state()->narrow($right->node, $right->apply(), $node);
        }
    }
}
