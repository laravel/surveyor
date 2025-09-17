<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use PhpParser\NodeAbstract;

trait CapturesConditionalChanges
{
    protected function startCapturing(NodeAbstract $node)
    {
        $this->scope->state()->startSnapshot($node);
    }

    protected function capture(NodeAbstract $node)
    {
        $this->scope->state()->endSnapshotAndCapture($node);
    }
}
