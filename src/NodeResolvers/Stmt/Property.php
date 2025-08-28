<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class Property extends AbstractResolver
{
    public function resolve(Node\Stmt\Property $node)
    {
        foreach ($node->props as $prop) {
            $name = $prop->name->name;
            dd($name);
        }
    }
}
