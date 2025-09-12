<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class HaltCompiler extends AbstractResolver
{
    public function resolve(Node\Stmt\HaltCompiler $node)
    {
        return null;
    }
}
