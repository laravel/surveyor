<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ShellExec extends AbstractResolver
{
    public function resolve(Node\Expr\ShellExec $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
