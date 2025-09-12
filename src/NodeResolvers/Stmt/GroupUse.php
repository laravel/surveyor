<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class GroupUse extends AbstractResolver
{
    public function resolve(Node\Stmt\GroupUse $node)
    {
        $prefix = $node->prefix->toString();

        foreach ($node->uses as $use) {
            if ($use->alias) {
                $this->scope->addUse($prefix.'\\'.$use->alias->name);
            } else {
                $this->scope->addUse($prefix.'\\'.$use->name->toString());
            }
        }

        return null;
    }
}
