<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\CapturesConditionalChanges;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Match_ extends AbstractResolver
{
    use CapturesConditionalChanges;

    public function resolve(Node\Expr\Match_ $node)
    {
        $this->scope->startConditionAnalysis();
        $this->from($node->cond);
        $this->scope->endConditionAnalysis();

        $armTypes = [];

        foreach ($node->arms as $arm) {
            foreach ($arm->conds ?? [] as $cond) {
                $this->scope->startConditionAnalysis();
                $this->from($cond);
                $this->scope->endConditionAnalysis();
            }

            $this->startCapturing($arm);
            $armTypes[] = $this->from($arm->body);
            $this->capture($arm);
        }

        return Type::union(...$armTypes);
    }
}
