<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\MatchArm;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class MatchArm extends AbstractResolver
{
    public function resolve(Node\MatchArm $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
