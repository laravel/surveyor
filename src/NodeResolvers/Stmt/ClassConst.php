<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzed\ConstantResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassConst extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassConst $node)
    {
        $name = (string) $node->consts[0]->name;
        $type = $this->from($node->consts[0]->value);

        $this->scope->addConstant($name, $type);

        if (($result = $this->scope->result()) instanceof ClassResult) {
            $result->addConstant(new ConstantResult(name: $name, type: $type));
        }

        return null;
    }
}
