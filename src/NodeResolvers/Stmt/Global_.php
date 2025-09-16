<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Global_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Global_ $node)
    {
        foreach ($node->vars as $var) {
            if (! $var instanceof Node\Expr\Variable) {
                Debug::ddAndOpen($var, 'global: variable but not a variable??');
            }

            $scope = $this->scope;

            while ($scope && ! $scope->variables()->get($var->name)) {
                $scope = $scope->parent();
            }

            if ($scope) {
                $this->scope->variables()->add($var->name, $scope->variables()->get($var->name), $node);
            } else {
                Debug::ddAndOpen($var, $this->scope, 'global: variable not found in scope');
            }
        }

        return null;
    }
}
