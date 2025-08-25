<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class TraitUseAdaptation extends AbstractResolver
{
    public function resolve(Node\Stmt\TraitUseAdaptation $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
