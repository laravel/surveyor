<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Name extends AbstractResolver
{
    public function resolve(Node\Name $node)
    {
        if (in_array($node->name, ['self', 'parent', 'static'])) {
            return Type::from($this->scope->entityName());
        }

        return Type::from($this->scope->getUse($node->name));
    }
}
