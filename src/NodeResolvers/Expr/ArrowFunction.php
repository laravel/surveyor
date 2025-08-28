<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Expr;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\Type;
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

            $this->scope->stateTracker()->addVariable($param->var->name, Type::union(...$types), $node->getStartLine());
        }

        return $this->from($node->expr);
    }
}
