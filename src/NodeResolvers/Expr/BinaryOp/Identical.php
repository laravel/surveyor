<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\BinaryOp;

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Result\VariableState;
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
            $results = array_map(fn ($o) => $this->fromOutsideOfCondition($o), $other);

            // TODO: We're not catching ArrayDimFetch here yet, gotta return it from the updateArrayKey method I think
            foreach ($results as $index => $result) {
                if ($result instanceof VariableState) {
                    return new Condition($other[$index], $result->type());
                }
            }

            return null;
        }

        $type = $this->fromOutsideOfCondition($other[0]);

        if ($type === null) {
            Debug::ddAndOpen($other[0], $node, 'type is null?');
        }

        if ($type instanceof VariableState) {
            return new Condition($variable, $type->type());
        }

        return new Condition($variable, $type);
    }
}
