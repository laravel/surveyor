<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\InterpolatedStringPart;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class InterpolatedStringPart extends AbstractResolver
{
    public function resolve(Node\InterpolatedStringPart $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
