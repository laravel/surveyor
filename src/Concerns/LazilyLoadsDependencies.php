<?php

namespace Laravel\Surveyor\Concerns;

use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Parser\Parser;
use Laravel\Surveyor\Resolvers\NodeResolver;

trait LazilyLoadsDependencies
{
    protected DocBlockParser $docBlockParser;

    protected NodeResolver $nodeResolver;

    protected Parser $parser;

    protected Analyzer $analyzer;

    protected function getDocBlockParser(): DocBlockParser
    {
        return $this->docBlockParser ??= app(DocBlockParser::class);
    }

    protected function getNodeResolver(): NodeResolver
    {
        return $this->nodeResolver ??= app(NodeResolver::class);
    }

    protected function getParser(): Parser
    {
        return $this->parser ??= app(Parser::class);
    }

    protected function getAnalyzer(): Analyzer
    {
        return $this->analyzer ??= app(Analyzer::class);
    }
}
