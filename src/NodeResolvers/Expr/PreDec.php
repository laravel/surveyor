<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PreDec extends AbstractResolver
{
    public function resolve(Node\Expr\PreDec $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
