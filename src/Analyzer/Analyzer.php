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
        if ($path === '') {
            Debug::log('⚠️ No path provided to analyze.');

            return $this;
        }

        Debug::addPath($path);

        if ($cached = AnalyzedCache::get($path)) {
            Debug::log("🎁 Using cached analysis: {$path}");

            $this->analyzed = $cached;

            return $this;
        }

        if (AnalyzedCache::isInProgress($path)) {
            Debug::log("🔄 Analysis in progress: {$path}");

            return;
        }

        AnalyzedCache::inProgress($path);

        Debug::log("🧠 Analyzing: {$path}");

        $this->analyzed = $this->parser->parse(file_get_contents($path), $path);

        AnalyzedCache::add($path, $this->analyzed);

        Debug::removePath($path);

        return $this;
    }

    public function analyzed()
    {
        return $this->analyzed;
    }
}
