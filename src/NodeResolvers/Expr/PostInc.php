<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PostInc extends AbstractResolver
{
    public function resolve(Node\Expr\PostInc $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
