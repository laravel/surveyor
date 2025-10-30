<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\Parser;
use Laravel\Surveyor\Resolvers\NodeResolver;
use ReflectionClass;

class Analyzer
{
    protected Scope $analyzed;

    public function __construct(
        protected Parser $parser,
        protected NodeResolver $resolver,
    ) {
        //
    }

    public function analyzeClass(string $className)
    {
        return $this->analyze(new ReflectionClass(($className))->getFileName());
    }

    public function analyze(string $path)
    {
        $shortPath = str_replace($_ENV['HOME'], '~', $path);

        if ($path === '') {
            Debug::log('⚠️ No path provided to analyze.');

            return $this;
        }

        Debug::addPath($path);

        if ($cached = AnalyzedCache::get($path)) {
            Debug::log("🎁 Using cached analysis: {$shortPath}");

            $this->analyzed = $cached;

            return $this;
        }

        if (AnalyzedCache::isInProgress($path)) {
            Debug::log("⏳ Waiting for analysis to complete: {$shortPath}");

            return $this;
        }

        AnalyzedCache::inProgress($path);

        Debug::log("🧠 Analyzing: {$shortPath}");

        $analyzed = $this->parser->parse(file_get_contents($path), $path);

        foreach ($analyzed as $result) {
            if ($result->fullPath() === $path) {
                $this->analyzed = $result;
            }

            AnalyzedCache::add($result->fullPath(), $result);
        }

        Debug::removePath($path);

        return $this;
    }

    public function analyzed(): ?Scope
    {
        return $this->analyzed ?? null;
    }

    public function result()
    {
        return $this->analyzed->result();
    }
}
