<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt\TraitUseAdaptation;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Alias extends AbstractResolver
{
    public function resolve(Node\Stmt\TraitUseAdaptation\Alias $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
