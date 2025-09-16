<?php

namespace Laravel\Surveyor\NodeResolvers\Expr\AssignOp;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Mod extends AbstractResolver
{
    public function resolve(Node\Expr\AssignOp\Mod $node)
    {
        return null;
    }
}
