<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Coalesce extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Coalesce $node)
    {
        return null;
    }
}
