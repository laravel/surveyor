<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Global_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Global_ $node)
    {
        foreach ($node->vars as $var) {
            if (! $var instanceof Node\Expr\Variable || ! is_string($var->name)) {
                continue;
            }

            $scope = $this->scope;

            while ($scope && ! $scope->state()->variables()->get($var->name)) {
                $scope = $scope->parent();
            }

            $type = $scope?->state()->variables()->get($var->name);

            if ($type) {
                $this->scope->state()->add($var, $type);
            }
        }

        return null;
    }
}
