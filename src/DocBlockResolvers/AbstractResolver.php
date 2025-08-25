<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers;

use Illuminate\Support\Arr;
use Laravel\StaticAnalyzer\Parser\DocBlockParser;
use Laravel\StaticAnalyzer\Resolvers\DocBlockResolver;
// use Laravel\StaticAnalyzer\Parser\Parser;
// use Laravel\StaticAnalyzer\Reflector;
use PhpParser\Node\Expr\CallLike;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

abstract class AbstractResolver
{
    // TODO: Do we need all of these props?
    public function __construct(
        public DocBlockResolver $typeResolver,
        // protected Reflector $reflector,
        // protected Parser $parser,
        protected DocBlockParser $docBlockParser,
        protected PhpDocNode $parsed,
        public array $context = [],
        protected ?CallLike $referenceNode = null,
    ) {
        //
    }

    protected function from(Node $node)
    {
        return $this->typeResolver->from($node, $this->context);
    }

    // protected function union(...$types): string
    // {
    //     return collect($types)
    //         ->map(fn($type) => collect(Arr::wrap($type))->map(fn($t) => explode('|', $t)))
    //         ->flatten()
    //         ->map(fn($t) => trim($t))
    //         ->unique()
    //         ->implode(' | ');
    // }
}
