<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use PhpParser\Node;

trait ResolvesArrays
{
    protected function resolveArrayVarAndKeys(
        Node\Expr\ArrayDimFetch $node,
    ) {
        $keys = [];
        $var = $node;

        while ($var instanceof Node\Expr\ArrayDimFetch) {
            if ($var->dim === null) {
                break;
            }

            $result = $this->fromOutsideOfCondition($var->dim);

            if (! property_exists($result, 'value')) {
                break;
            }

            $keys[] = $result->value;
            $var = $var->var;
        }

        $keys = array_reverse($keys);

        return [$var, $keys];
    }
}
