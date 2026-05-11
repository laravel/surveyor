<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Analyzed\ConstantResult;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ClassConst extends AbstractResolver
{
    public function resolve(Node\Stmt\ClassConst $node)
    {
        $result = $this->scope->result();

        foreach ($node->consts as $const) {
            $name = (string) $const->name;
            $type = $this->from($const->value);

            $this->scope->addConstant($name, $type);

            if ($result instanceof ClassLikeResult) {
                $result->addConstant(new ConstantResult(name: $name, type: $type));
            }
        }

        return null;
    }
}
