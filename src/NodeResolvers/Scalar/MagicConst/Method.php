<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Scalar\MagicConst;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Method extends AbstractResolver
{
    public function resolve(Node\Scalar\MagicConst\Method $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
