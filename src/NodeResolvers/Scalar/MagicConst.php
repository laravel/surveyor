<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class MagicConst extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
