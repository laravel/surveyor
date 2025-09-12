<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PostDec extends AbstractResolver
{
    public function resolve(Node\Expr\PostDec $node)
    {
        return null;
    }
}
