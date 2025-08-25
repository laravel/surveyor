<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar\MagicConst;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Dir extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Dir $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
