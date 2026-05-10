# Performance Notes

Working notes on speed and memory of the analyzer. Read this before re-doing performance work — it captures what was tried, what worked, what didn't, and the surprising things we learned along the way.

## Measurements

Bench: analyze `workbench/app/` (23 user files → 257 cached scopes via recursive analysis) twice in a single PHP process.

| | `main` baseline | this branch |
|---|---|---|
| Time / iter | 0.575s | **0.426s (−26%)** |
| Peak memory | 94.5 MB | 94.5 MB (unchanged) |
| Serialized cache | 5.92 MB | 5.21 MB (−12%) |

The speed win is real and reproducible. The memory work is real but does not move peak memory on this workload — see "Why peak didn't move" below.

## Where memory actually goes (94 MB peak, 23 workbench files)

| Source | Approx | Notes |
|---|---|---|
| PHP class table | 30–50 MB | 1,674 declared classes — Laravel framework + Carbon + Symfony, autoloaded via reflection. Cannot be reclaimed in-process. |
| DocBlockParser cache | ~12 MB | 3,573 cached PhpDocNode ASTs. ~30% of bytes are 4 mega-docblocks (Carbon's auto-generated 800 KB ones). |
| Transient parser AST during analysis | ~12 MB | peak (106) − resident (94). Released after each `Analyzer::analyze` returns, but PHP keeps the chunks. |
| AnalyzedCache scopes | ~5 MB | 257 file scopes (post-Phase-C slimming). |
| `Util::$isClassOrInterface` static | 1–2 MB | 14,380 string→bool entries. |
| Other (reflection, traversal scratch) | rest | |

## Speed wins that landed

Ordered by impact:

1. **Removed per-node Debug overhead** — `Debug::increaseDepth/decreaseDepth` were called on every AST node enter/leave, each calling `array_keys(self::$paths)` which allocated a fresh array per call. ~21% of total runtime. Now `increaseDepth/decreaseDepth` self-gate on log level, and the per-node `Debug::log` calls in `TypeResolver` and `AbstractResolver::from` are commented out.
2. **`Type::union` Collection→array** — replaced the `collect()->flatMap->filter->map->unique->filter->filter` pipeline with a single foreach. ~11% on this workload.
3. **`StateTrackerItem::findAtLine` foreach vs `array_filter`+`end`/`prev`** — eliminated per-element closure dispatch. ~2.6%.
4. **Scope save/restore in `NodeResolver::fromWithScope`** — enabled resolver instance pooling without scope corruption during recursive `Analyzer::analyze` calls. Negligible on bench but **required for correctness** if pooling is used (see below).

The numbers don't add cleanly because the optimizations overlap. Total: ~26% speedup, dominated by the Debug overhead removal.

## Memory wins that landed

1. **Slim cached scopes** (`Scope::state()->compact()`): drops local-variable history (dead post-traversal), collapses property-tracker history to last-state-only (cross-file consumers only ever read the latest via `properties()->get($name)`), clears all snapshots. Called from `AnalyzedCache::add` before storage. **Validated by parallel-comparison probe** — confirmed `properties()->get()` returns identical `VariableState` instances before/after compaction across the entire test suite.
2. **Type singleton interning**: `Type::mixed()`, `Type::int()` (no value), `Type::string()` (no value), etc. return shared instances. Required changing `nullable()`/`required()`/`optional()` from in-place mutators to clone-and-return so singletons can't be corrupted. Patched 4 callsites that had been discarding the return value (`NullableType.php:13`, `ModelAnalyzer.php:39`, `ResourceAnalyzer.php:497`, `UnionType.php:54`). **Validated by post-analysis invariant probe** — confirmed every singleton has its class-default flags after a full analysis pass.

## Why peak memory didn't move

PHP's Zend Memory Manager allocates in 2–4 MB chunks. Once chunks are claimed from the OS, they stay claimed for the process lifetime — even after `unset()` or GC. See: [The memory pattern every PHP developer should know](https://butschster.medium.com/the-memory-pattern-every-php-developer-should-know-about-long-running-processes-d3a03b87271c).

Implications:

- The 94 MB peak is **set during the first analysis pass** when the deepest recursive `Analyzer::analyze` chain is in flight (with all transient ASTs simultaneously alive). After that, subsequent iterations and cache slimming reuse already-claimed chunks; they don't release them.
- `AnalyzedCache::clearMemory()` empties the array but doesn't reduce process memory.
- Cache slimming via `compact()` **prevents the high-water mark from being even higher**, but on this workload the cache (~5 MB) wasn't the dominant driver.

To actually lower peak, the levers are:
- **Reduce the deepest recursive analysis chain** (each level of `Reflector::methodReturnType` → `Analyzer::analyze` adds an in-flight AST + visitor scope).
- **Subprocess isolation** — run analysis in a worker that exits when done, letting the OS reclaim. Relevant for embedded usage (language servers, daemons), not for one-shot CLI.
- **Stream results to disk and discard** — never accumulate. Conflicts with cross-file queries the analyzer needs.

## Things tried and rejected

- **Caching `Reflector::reflectClass()` results harder** — already cached.
- **Eager Debug arg-eval avoidance via closures** — closure allocation costs the same as the string concat we'd be deferring. Net wash. The fix was deleting the per-node logs entirely.
- **Bounding `DocBlockParser::$cached` (LRU on entry count)** — measured: any cap that drops frequently-reused docblocks (especially Carbon's class-level docblock, hit on every `$date->method()` call) hurts more than it saves. The 12 MB cost is paying its way.
- **Skipping caching of giant docblocks (Carbon, > 100 KB)** — initially looked attractive (saves ~4 MB). Wrong: those huge docblocks are looked up *repeatedly* across a real codebase (every Carbon usage triggers reflection on the Carbon class). The cache is doing real work for them; without it, parsing 800 KB on every Carbon-method usage would be catastrophic on real apps.
- **`SplFixedArray` / fixed-width array tricks** — not applicable. Our growth-bearing arrays are dynamic; the small fixed structures (`VariableState` fields) are already PHP 8 typed properties.
- **Naive `AnalyzedCache` LRU eviction without disk** — by definition forces re-analysis on miss, undoing the speed work.
- **Opt-in `AnalyzedCache` LRU + disk fallback** — built and benched (`setMemoryLimit($n)` capping in-memory entries with disk eviction). Cap was correctly enforced (in-memory dropped to 50/100/etc. as configured) but **delivered no measurable peak memory reduction** on the workbench — peak is set by the in-flight transient ASTs during recursive analysis, not by the cumulative cache. Reverted; would only matter at 100k-file scale or in long-running embedded usage. If revisited: implementation was an LRU touch on `tryFromMemory` hits + `enforceMemoryLimit()` after `add()`/`tryFromDisk()`, gated on `persistToDisk` being true (lossless eviction).
- **Type::union with `->unique(toString)` against `json_encode`-based `id()`** — the cached scalar `id()` (Phases B done as part of speed work) avoids recursive json_encode on every uniqueness check.
- **Resolver instance pool without scope save/restore** — initially tried and broke `ModelAnalyzer`/`ResourceAnalyzer` tests. Recursive `Analyzer::analyze` reuses cached resolver instances, clobbering the outer caller's `$this->scope`. Fixed via save/restore in `NodeResolver::fromWithScope` and `exitNode`. Regression test at `tests/Unit/Resolvers/NodeResolverPoolTest.php` codifies this — keep it green.

## Validation technique: parallel-comparison harness

When changing a hot path that has subtle behavior (`StateTrackerItem::findAtLine`, `Scope::compact`), the cleanest validation is to run **both old and new logic in parallel** and assert identical results, then run the full test suite. Used successfully for:

- `findAtLine` (foreach vs `array_filter`+`end`/`prev`) — wrapped both, asserted identical `VariableState` instance returned.
- `compact()` correctness — snapshotted `properties()->get($name)` for every property *before* compacting, asserted same instances after.
- Type singleton invariant — after a full analysis pass, asserted every interned singleton still matched its class default (`nullable=false, required=true` for most; `nullable=true` for `NullType`).

If the test suite catches no divergence across hundreds of analysis flows, the change is behaviorally equivalent to the predecessor.

## Open territory

For someone picking this up later:

1. **Reduce per-file peak during deep recursion.** The biggest unclaimed memory win. When analyzing fileA triggers `analyze(B) → analyze(C) → analyze(D)`, all 4 parser ASTs and visitor scopes coexist. Could the recursive analyses be deferred / serialized to disk and queued for a second pass?
2. **Disk-backed type interning for `ClassType`/`ArrayType` etc.** Similar to how the docblock cache stays large because of repeated lookups, the type system creates many `ClassType` instances for the same class. Interning by `value` (resolved class name) would dedupe.
3. **`Util::$isClassOrInterface` size** — 14,380 entries on the workbench. Most are negative results for things that aren't classes (e.g., every AST node class name we ever processed). Skipping negative-result caching for transient strings might shave 1–2 MB.
4. **`Reflector::propertyType` and `methodReturnType`** — each can trigger `Analyzer::analyze` on another file. That's the recursive-analysis explosion vector. Caching reflection results more aggressively (or pre-warming framework reflections) could reduce the cascade.
5. **`DocBlockParser::parse` parses the whole AST eagerly** — even when the caller only needs `getReturnTagValues()`. Lazy/partial parsing would reduce per-docblock cost (matters more for the 800 KB Carbon docblocks).

## How to bench

A simple, controlled bench script (drop it in `tests/bench.php` and run with `php tests/bench.php [iterations]`):

```php
<?php
require __DIR__.'/../vendor/autoload.php';

use Illuminate\Container\Container;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\SurveyorServiceProvider;
use Orchestra\Testbench\Foundation\Application as TestbenchApplication;

$app = TestbenchApplication::create(realpath(__DIR__.'/../workbench'));
$app->register(SurveyorServiceProvider::class);
Container::setInstance($app);

$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../workbench/app')) as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') $files[] = $f->getPathname();
}

$iters = (int) ($argv[1] ?? 10);
$analyzer = $app->make(Analyzer::class);

$start = microtime(true);
for ($i = 0; $i < $iters; $i++) {
    AnalyzedCache::clearMemory();
    foreach ($files as $f) $analyzer->analyze($f);
}
printf("%.3fs/iter, peak %.1fMB\n",
    (microtime(true) - $start) / $iters,
    memory_get_peak_usage(true) / 1024 / 1024);
```

Requires `mkdir -p workbench/bootstrap/cache` once before first run.

For comparing before/after a change, **always run each variant in a fresh PHP process** (don't compare back-to-back in one process — chunk allocation state carries over and skews readings). `git stash` + run + `git stash pop` works.

For correctness comparisons under refactoring, prefer the parallel-comparison harness over speed benches.
