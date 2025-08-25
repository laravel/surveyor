<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;
use ReflectionFunction;

class FuncCall extends AbstractResolver
{
    public function resolve(Node\Expr\FuncCall $node)
    {
        $returnTypes = [];

        $name = $node->name->toString();

        $reflection = new ReflectionFunction($name);

        if ($reflection->hasReturnType()) {
            $returnTypes[] = Type::from($reflection->getReturnType());
        }

        if ($reflection->getDocComment()) {
            $result = $this->docBlockParser->parseReturn($reflection->getDocComment(), $node);

            if ($result) {
                array_push($returnTypes, ...$result);
            }
        }

        // TODO: For things like `view`, `config` it would be nice to get the args as well

        return Type::union(...$returnTypes);
    }
}
