<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class PreDec extends AbstractResolver
{
    public function resolve(Node\Expr\PreDec $node)
    {
        return null;
    }
}
