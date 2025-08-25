<?php

namespace Laravel\StaticAnalyzer\Analyzer;

use Laravel\StaticAnalyzer\Parser\Parser;
use Laravel\StaticAnalyzer\Resolvers\NodeResolver;

class Analyzer
{
    public function __construct(
        protected Parser $parser,
        protected NodeResolver $resolver,
    ) {
        //
    }

    public function analyze(string $path)
    {
        $parsed = $this->parser->parse(file_get_contents($path));

        $resolved = array_map(fn($node) => $this->resolver->from($node), $parsed);

        dd($resolved);
    }
}
