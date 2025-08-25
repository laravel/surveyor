<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt\TraitUseAdaptation;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Precedence extends AbstractResolver
{
    public function resolve(Node\Stmt\TraitUseAdaptation\Precedence $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
