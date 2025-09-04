<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Unset_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Unset_ $node)
    {
        foreach ($node->vars as $var) {
            if ($var instanceof Node\Expr\Variable) {
                $this->scope->variables()->unset($var->name, $node->getStartLine());
            } elseif ($var instanceof Node\Expr\PropertyFetch) {
                $this->scope->properties()->unset($var->name->name, $node->getStartLine());
            } else {
                dd('unset: not a variable or property fetch??', $var);
            }
        }

        return null;
    }
}
