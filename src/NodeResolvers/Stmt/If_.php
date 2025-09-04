<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class If_ extends AbstractResolver
{
    public function resolve(Node\Stmt\If_ $node)
    {
        $this->scope->variables()->startSnapshot($node->getStartLine());
        // $changed = $this->tracker->endVariableSnapshot($ifStmt->getStartLine());
    }
}
