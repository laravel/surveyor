<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\CapturesConditionalChanges;
use PhpParser\Node;
use PhpParser\NodeAbstract;

class Else_ extends AbstractResolver
{
    use CapturesConditionalChanges;

    public function resolve(Node\Stmt\Else_ $node)
    {
        $this->startCapturing($node);
    }

    public function onExit(NodeAbstract $node)
    {
        $this->capture($node);
    }
}
