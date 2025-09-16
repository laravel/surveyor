<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\CapturesConditionalChanges;
use PhpParser\Node;
use PhpParser\NodeAbstract;

class If_ extends AbstractResolver
{
    use CapturesConditionalChanges;

    public function resolve(Node\Stmt\If_ $node)
    {
        $this->startCapturing($node);

        // Analyze the condition for type narrowing
        $this->scope->startConditionAnalysis();
        $this->from($node->cond);
        $this->scope->endConditionAnalysis();
    }

    public function onExit(NodeAbstract $node)
    {
        $this->capture($node);
    }
}
