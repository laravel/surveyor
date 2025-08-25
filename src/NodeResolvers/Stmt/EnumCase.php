<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class EnumCase extends AbstractResolver
{
    public function resolve(Node\Stmt\EnumCase $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
