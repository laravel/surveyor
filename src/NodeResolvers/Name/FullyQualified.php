<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Name;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class FullyQualified extends AbstractResolver
{
    public function resolve(Node\Name\FullyQualified $node)
    {
        return Type::from($node->toString());
    }
}
