<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Match_ extends AbstractResolver
{
    public function resolve(Node\Expr\Match_ $node)
    {
        return Type::union(
            ...array_map(
                fn ($arm) => $this->from($arm->body),
                $node->arms,
            ),
        );
    }
}
