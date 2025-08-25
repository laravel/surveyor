<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\VariadicPlaceholder;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class VariadicPlaceholder extends AbstractResolver
{
    public function resolve(Node\VariadicPlaceholder $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
