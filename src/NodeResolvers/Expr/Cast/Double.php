<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\Cast;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Double extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\Double $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
