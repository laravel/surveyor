<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr\BinaryOp;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;

class Mod extends AbstractResolver
{
    public function resolve(Node\Expr\BinaryOp\Mod $node)
    {
        return Type::number();
    }

    public function resolveForCondition(Node\Expr\BinaryOp\Mod $node)
    {
        //
    }
}
