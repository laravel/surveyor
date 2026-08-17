<?php

namespace Laravel\Surveyor\Analyzer;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\Parser;
use ReflectionClass;

class Analyzer
{
    protected Scope $analyzed;

    protected int $analyzing = 0;

    public function __construct(
        protected Parser $parser,
    ) {
        //
    }

    public function analyzeClass(string $className)
    {
        return $this->analyze((new ReflectionClass($className))->getFileName() ?: '');
    }

    public function analyze(string $path)
    {
        if ($path === '') {
            Debug::log('⚠️ No path provided to analyze.');

            $this->analyzed = new Scope;

            return $this;
        }

        AnalyzedCache::addDependency($path);

        $this->analyzing++;

        Debug::addPath($path);

        if ($cached = AnalyzedCache::get($path)) {
            Debug::log(static fn () => '🎁 Using cached analysis: '.self::shortPath($path));

            $this->analyzed = $cached;

            $this->analyzing--;

            return $this;
        }

        if (AnalyzedCache::isInProgress($path)) {
            AnalyzedCache::noteCycle($path);

            Debug::log(static fn () => '⏳ Waiting for analysis to complete: '.self::shortPath($path));

            // The in-progress scope isn't available yet, so anything still held
            // in $analyzed belongs to an unrelated file.
            $this->analyzed = new Scope;

            $this->analyzing--;

            return $this;
        }

        AnalyzedCache::inProgress($path);

        Debug::log(static fn () => '🧠 Analyzing: '.self::shortPath($path));

        AnalyzedCache::beginAnalysis($path);

        try {
            $analyzed = $this->parser->parse(file_get_contents($path), $path);

            foreach ($analyzed as $result) {
                if ($result->fullPath() === $path) {
                    $this->analyzed = $result;
                }

                AnalyzedCache::add($result->fullPath(), $result);
            }
        } finally {
            AnalyzedCache::endAnalysis($path);
        }

        Debug::removePath($path);

        $this->analyzing--;

        return $this;
    }

    protected static function shortPath(string $path): string
    {
        return str_replace($_ENV['HOME'] ?? '', '~', $path);
    }

    public function analyzed(): ?Scope
    {
        return $this->analyzed ?? null;
    }

    public function result()
    {
        return $this->analyzed()?->result();
    }
}
