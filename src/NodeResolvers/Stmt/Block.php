<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Block extends AbstractResolver
{
    public function resolve(Node\Stmt\Block $node)
    {
        return null;
    }
}
