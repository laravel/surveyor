<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class InlineHTML extends AbstractResolver
{
    public function resolve(Node\Stmt\InlineHTML $node)
    {
        return null;
    }
}
