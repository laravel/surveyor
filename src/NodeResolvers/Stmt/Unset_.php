<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Unset_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Unset_ $node)
    {
        foreach ($node->vars as $var) {
            if (!$var instanceof Node\Expr\ArrayDimFetch) {
                $this->scope->state()->unset($var, $node);
                continue;
            }

            if ($var->dim === null) {
                Debug::ddAndOpen($node, $var, 'unset: array dim fetch but dim is null??');
            }

            $dim = $this->from($var->dim);

            if ($dim->value === null) {
                // Couldn't figure out the dim, so we can't unset the array key
                // TODO: May need to circle back on this
                continue;
            }

            $this->scope->state()->unsetArrayKey($var->var, $dim->value, $node);
        }

        return null;
    }
}
