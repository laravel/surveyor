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

    protected bool $settling = false;

    public function __construct(
        protected Parser $parser,
    ) {
        // Deciding whether a cached entry still holds means knowing what its
        // dependencies look like now, and only an analysis can say.
        AnalyzedCache::resolveSurfaceUsing(function (string $path): ?string {
            $analyzed = $this->analyze($path)->analyzed();

            return $analyzed === null ? null : Surface::hash($analyzed);
        });
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

        $this->settle();

        Debug::removePath($path);

        $this->analyzing--;

        return $this;
    }

    /**
     * Analyze the members of a cycle again now that it has closed.
     *
     * Each of them ran while another member was still open, so wherever they
     * reached for it they resolved against an empty scope. Which member that
     * was depends on where the analysis happened to start, so leaving those
     * answers in place makes the cache depend on visit order.
     *
     * Only the members that gave up on a file themselves are looked at to begin
     * with. A member that merely asked one of those is looked at when, and only
     * when, the answer it was given moved, which is what the surface hashes are
     * for. One member is analyzed at a time with the rest left in the cache, so
     * each sees a finished answer for everything it reaches.
     */
    protected function settle(): void
    {
        if ($this->settling) {
            return;
        }

        $members = AnalyzedCache::takeSettled();

        if ($members === []) {
            return;
        }

        $mine = $this->analyzed ?? null;
        $this->settling = true;

        try {
            $this->settleMembers($members);
        } finally {
            $this->settling = false;

            // Anything a member reached while settling has been analyzed with
            // the cycle closed, so there is nothing left to settle.
            AnalyzedCache::takeSettled();

            if ($mine !== null) {
                $this->analyzed = $mine;
            }
        }
    }

    /**
     * @param  list<array{path: string, bailed: bool}>  $members
     */
    protected function settleMembers(array $members): void
    {
        $waiting = [];

        foreach ($members as $member) {
            $waiting[$member['path']] = $member['bailed'];
        }

        $queue = array_keys(array_filter($waiting));

        while ($queue !== []) {
            $moved = [];

            foreach ($queue as $path) {
                unset($waiting[$path]);

                if ($this->reanalyze($path)) {
                    $moved[$path] = true;
                }
            }

            $queue = [];

            foreach ($waiting as $path => $bailed) {
                foreach (AnalyzedCache::dependenciesOf($path) as $dependency) {
                    if (isset($moved[$dependency])) {
                        $queue[] = $path;

                        break;
                    }
                }
            }
        }
    }

    /**
     * Analyze a path again, and say whether what dependents can see of it moved.
     */
    protected function reanalyze(string $path): bool
    {
        $before = AnalyzedCache::surfaceHash($path);

        AnalyzedCache::invalidate($path);

        Debug::log(static fn () => '♻️ Re-analyzing settled cycle member: '.self::shortPath($path));

        $this->analyze($path);

        return AnalyzedCache::surfaceHash($path) !== $before;
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
