<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Comment;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class Comment extends AbstractResolver
{
    public function resolve(Ast\Comment $node)
    {
        dd($node, $node::class . ' not implemented yet');
    }
}
