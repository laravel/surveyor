<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Label extends AbstractResolver
{
    public function resolve(Node\Stmt\Label $node)
    {
        return null;
    }
}
