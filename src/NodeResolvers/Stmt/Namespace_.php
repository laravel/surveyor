<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Namespace_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Namespace_ $node)
    {
        $this->scope->setNamespace($node->name->name);

        return array_map(fn ($node) => $this->from($node), $node->stmts);
    }
}
