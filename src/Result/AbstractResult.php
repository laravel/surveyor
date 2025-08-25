<?php

namespace Laravel\StaticAnalyzer\Result;

use PhpParser\NodeAbstract;

abstract class AbstractResult
{
    protected int $startLine;

    protected int $endLine;

    public function fromNode(NodeAbstract $node)
    {
        $this->startLine = $node->getStartLine();
        $this->endLine = $node->getEndLine();

        return $this;
    }
}
