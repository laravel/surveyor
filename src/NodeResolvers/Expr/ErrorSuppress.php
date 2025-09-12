<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ErrorSuppress extends AbstractResolver
{
    public function resolve(Node\Expr\ErrorSuppress $node)
    {
        return null;
    }
}
