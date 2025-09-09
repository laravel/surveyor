<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\Analysis\Condition;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
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

        // TODO: Not sure this is correct
        if ($left instanceof Condition) {
            $this->scope->variables()->narrow($left->variable, $left->apply(), $node->getStartLine());
        }

        if ($right instanceof Condition) {
            $this->scope->variables()->narrow($right->variable, $right->apply(), $node->getStartLine());
        }
    }
}
