<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Debug\Debug;
use PhpParser\Node;

class ClosureUse extends AbstractResolver
{
    public function resolve(Node\ClosureUse $node)
    {
        if ($node->byRef) {
            $this->scope->state()->add(
                $node->var->name,
                $this->from($node->var),
                $node,
            );
        } else {
            Debug::ddAndOpen($node, 'figure out closure use');
        }
    }
}
