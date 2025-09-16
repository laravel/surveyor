<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\Parser;
use Laravel\Surveyor\Resolvers\NodeResolver;

class Analyzer
{
    protected array $analyzed = [];

    public function __construct(
        protected Parser $parser,
        protected NodeResolver $resolver,
    ) {
        //
    }

    public function analyze(string $path)
    {
        Debug::depth(0);

        if ($cached = AnalyzedCache::get($path)) {
            $this->analyzed = $cached;

            return $this;
        }

        if (AnalyzedCache::isInProgress($path)) {
            return;
        }

        AnalyzedCache::inProgress($path);

        if ($path === '') {
            Debug::log('⚠️  No path provided to analyze.');

            return $this;
        }

        Debug::log("🧠 Analyzing: {$path}");

        $this->analyzed = $this->parser->parse(file_get_contents($path), $path);

        AnalyzedCache::add($path, $this->analyzed);

        return $this;
    }

    public function analyzed()
    {
        return $this->analyzed;
    }
}
