<?php

namespace Laravel\StaticAnalyzer\NodeResolvers;

use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Name extends AbstractResolver
{
    public function resolve(Node\Name $node)
    {
        if (in_array($node->name, ['self', 'parent', 'static'])) {
            return Type::from($this->scope->className());
        }

        dd('nope');
    }
}
