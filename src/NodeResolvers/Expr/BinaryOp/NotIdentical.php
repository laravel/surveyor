<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class NotIdentical extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\NotIdentical $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\BinaryOp\NotIdentical $node)
    {
        $left = $node->left;
        $right = $node->right;

        if ($left instanceof Node\Expr\Variable && $right instanceof Node\Expr\Variable) {
            dd('left and right are variables??', $left, $right);
        }

        $variable = null;
        $other = null;

        if ($left instanceof Node\Expr\Variable) {
            $variable = $left;
            $other = $right;
        } elseif ($right instanceof Node\Expr\Variable) {
            $variable = $right;
            $other = $left;
        }

        if ($other instanceof Node\Expr\ConstFetch) {
            if ($other->name->toString() === 'null') {
                $this->scope->variables()->removeType($variable->name, $node->getStartLine(), Type::null());

                return;
            } else {
                dd('other is a const fetch but not null??', $other);
            }
        }

        dd('not identical: not a variable or const fetch??', $left, $right);
    }
}
