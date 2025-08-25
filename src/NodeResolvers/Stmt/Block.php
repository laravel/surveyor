<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Block extends AbstractResolver
{
    public function resolve(Node\Stmt\Block $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
