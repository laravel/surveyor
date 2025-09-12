<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PostInc extends AbstractResolver
{
    public function resolve(Node\Expr\PostInc $node)
    {
        return null;
    }
}
