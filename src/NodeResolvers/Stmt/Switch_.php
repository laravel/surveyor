<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\CapturesConditionalChanges;
use PhpParser\Node;

class Switch_ extends AbstractResolver
{
    use CapturesConditionalChanges;

    public function resolve(Node\Stmt\Switch_ $node)
    {
        $this->scope->startConditionAnalysis();
        $result = $this->from($node->cond);
        $this->scope->endConditionAnalysis();

        if ($result !== null) {
            Debug::ddAndOpen($result, $node, 'result is not null in switch');
        }

        // TODO: We're not doing anything with this yet, we... should
        $currentConditions = [];

        foreach ($node->cases as $case) {
            if ($case->cond !== null) {
                $this->scope->startConditionAnalysis();
                $currentConditions[] = $this->from($case->cond);
                $this->scope->endConditionAnalysis();
            }

            if (count($case->stmts) === 0) {
                continue;
            }

            $this->startCapturing($case);

            foreach ($case->stmts as $stmt) {
                $this->from($stmt);
            }

            $this->capture($case);
        }
    }
}
