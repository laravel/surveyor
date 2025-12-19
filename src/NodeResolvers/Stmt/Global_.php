<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Global_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Global_ $node)
    {
        foreach ($node->vars as $var) {
            if (! $var instanceof Node\Expr\Variable) {
                continue;
            }

            $scope = $this->scope;

            while ($scope && ! $scope->variables()->get($var->name)) {
                $scope = $scope->parent();
            }

            if ($scope) {
                $this->scope->state()->add($var, $scope->state()->get($var->name), $node);
            }
        }

        return null;
    }
}
