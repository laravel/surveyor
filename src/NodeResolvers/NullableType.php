<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\NullableType;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class NullableType extends AbstractResolver
{
    public function resolve(Node\NullableType $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
