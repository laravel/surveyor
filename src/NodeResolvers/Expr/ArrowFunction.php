<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class ArrowFunction extends AbstractResolver
{
    public function resolve(Node\Expr\ArrowFunction $node)
    {
        foreach ($node->params as $param) {
            $types = [];

            if ($param->default) {
                $types[] = $this->from($param->default);
            }

            if ($param->type) {
                $types[] = $this->from($param->type);
            }

            if (empty($types)) {
                $types[] = Type::mixed();
            }

            $this->scope->state()->add($param, Type::union(...$types));
        }

        return $this->from($node->expr);
    }
}
