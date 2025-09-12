<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Identical extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Identical $node)
    {
        return Type::bool();
    }

    public function resolveForCondition(Node\Expr\BinaryOp\Identical $node)
    {
        $left = $node->left;
        $right = $node->right;

        if ($left instanceof Node\Expr\Variable && $right instanceof Node\Expr\Variable) {
            return;
        }

        $variable = null;
        $other = [];

        if ($left instanceof Node\Expr\Variable) {
            $variable = $left;
            $other = [$right];
        } elseif ($right instanceof Node\Expr\Variable) {
            $variable = $right;
            $other = [$left];
        } else {
            $other = [$left, $right];
        }

        if ($variable === null) {
            return;
        }

        foreach ($other as $o) {
            if ($o instanceof Node\Expr\ConstFetch) {
                $type = $this->fromOutsideOfCondition($o);

                if ($type === null) {
                    Debug::ddAndOpen($o, $node, 'type is null?');
                }

                // TODO: Can we get multiple conditions? Or no?
                return new Condition($variable->name, $type);
            }
        }
    }
}
