<?php

namespace Laravel\StaticAnalyzer\NodeResolvers\Stmt;

use Laravel\StaticAnalyzer\NodeResolvers\AbstractResolver;
use PhpParser\Node;

class ElseIf_ extends AbstractResolver
{
    public function resolve(Node\Stmt\ElseIf_ $node)
    {
        $this->scope->variables()->startSnapshot($node->getStartLine());
        // $changed = $this->tracker->endVariableSnapshot($elseif->getStartLine());
    }
}
