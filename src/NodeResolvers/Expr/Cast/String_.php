<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\Cast;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\StringType;
use Laravel\StaticAnalyzer\Types\UnionType;
use PhpParser\Node;

class String_ extends AbstractResolver
{
    public function resolve(Node\Expr\Cast\String_ $node)
    {
        $type = $this->from($node->expr);

        if ($type instanceof UnionType) {
            $stringTypes = array_filter(
                $type->types,
                fn ($t) => $t instanceof StringType,
            );

            if (count($stringTypes) === 1) {
                return array_values($stringTypes)[0];
            }
        }

        if (! $type instanceof StringType) {
            dd('casting to string from non-string?', $type, $node->expr, $this->scope->className(), $this->scope->methodName());
        }

        return $type;
    }
}
