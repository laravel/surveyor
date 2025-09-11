<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Equal extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Equal $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\BinaryOp\Equal $node)
    {
        $left = $this->from($node->left);
        $right = $this->from($node->right);

        // TODO: Not sure this is correct
        if ($left instanceof Condition) {
            $this->scope->variables()->narrow($left->variable, $left->apply(), $node);
        }

        if ($right instanceof Condition) {
            $this->scope->variables()->narrow($right->variable, $right->apply(), $node);
        }
    }
}
