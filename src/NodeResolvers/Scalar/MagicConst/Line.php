<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar\MagicConst;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Line extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Line $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
