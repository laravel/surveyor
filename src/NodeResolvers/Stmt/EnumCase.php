<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class EnumCase extends AbstractResolver
{
    public function resolve(Node\Stmt\EnumCase $node)
    {
        $this->scope->addCase($node->name, $this->from($node->expr));

        // TODO: As we're analyzing, this should probably return something?
        return null;
    }
}
