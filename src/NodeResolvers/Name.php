<?php

namespace Laravel\Surveyor\NodeResolvers;

use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Name extends AbstractResolver
{
    public function resolve(Node\Name $node)
    {
        if (in_array($node->name, ['self', 'static'])) {
            return Type::from($this->scope->entityName());
        }

        if ($node->name === 'parent') {
            if (empty($this->scope->extends())) {
                return Type::mixed();
            }

            return Type::from($this->scope->extends()[0]);
        }

        return Type::from($this->scope->getUse($node->name));
    }

    public function resolveForCondition(Node\Name $node)
    {
        return $this->resolve($node);
    }
}
