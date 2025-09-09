<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\Analysis\Condition;
use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class BooleanAnd extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\BooleanAnd $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\BinaryOp\BooleanAnd $node)
    {
        $left = $this->from($node->left);
        $right = $this->from($node->right);

        if ($left instanceof Condition) {
            $this->scope->variables()->narrow($left->variable, $left->apply(), $node->getStartLine());
        }

        if ($right instanceof Condition) {
            $this->scope->variables()->narrow($right->variable, $right->apply(), $node->getStartLine());
        }
    }
}
