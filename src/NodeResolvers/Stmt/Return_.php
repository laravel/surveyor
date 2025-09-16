<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Return_ extends AbstractResolver
{
    public function resolve(Node\Stmt\Return_ $node)
    {
        // if ($node->getStartLine() === 52) {
        //     dd($this->scope()->variables());
        // }

        $this->scope->variables()->markSnapShotAsTerminated($node);
        $this->scope->properties()->markSnapShotAsTerminated($node);

        $result = Type::collapse($this->from($node->expr));

        $this->scope->addReturnType($result, $node->getStartLine());

        return null;
    }
}
