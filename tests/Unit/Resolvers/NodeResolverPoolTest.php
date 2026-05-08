<?php

use App\Models\User;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;

/**
 * `NodeResolver` caches one resolver instance per AST node class. Resolution
 * of a single AST node may, mid-flow, trigger a recursive `Analyzer::analyze`
 * call on a different file (e.g. via `Reflector::methodReturnType`,
 * `Reflector::propertyType`, `ResourceAnalyzer`, `ModelAnalyzer`, or `Param`'s
 * type-import resolution). That recursive traversal reuses the *same* cached
 * resolver instances, which means the inner traversal mutates `$this->scope`
 * on resolvers the outer caller is still mid-resolve on.
 *
 * `NodeResolver::fromWithScope` and `exitNode` snapshot the resolver's,
 * reflector's, and docBlockParser's scope, swap in the new scope, and restore
 * the snapshot in a `finally` block. If you ever refactor the pool or the
 * scope propagation, this test must keep passing — failure here means the
 * outer scope is being clobbered by the recursive inner traversal.
 *
 * The `ModelAnalyzerTest` and `ResourceAnalyzerTest` suites also exercise
 * this invariant (and were the original tripwire when the pool was first
 * added without save/restore). This test is restated here so that a future
 * refactor of `NodeResolver` produces an obvious, named failure pointing
 * directly at the resolver pool.
 */
describe('NodeResolver pool: recursive-analyze isolation', function () {
    beforeEach(function () {
        AnalyzedCache::clear();
    });

    afterEach(function () {
        AnalyzedCache::clear();
    });

    it('preserves outer Class_ scope when ModelAnalyzer triggers recursive analyze during onExit', function () {
        // The User model has a custom-cast attribute (`money`/`money_in_cents`)
        // and Eloquent relations (`posts`). Analyzing it forces:
        //   1. `Class_::onExit` runs `ModelAnalyzer::mergeIntoResult`
        //   2. Attribute iteration calls `Reflector::methodReturnType` for
        //      accessor methods → triggers recursive `Analyzer::analyze` on
        //      another file
        //   3. After that recursion returns, ModelAnalyzer continues iterating
        //      relations and adds `posts` as a method
        //
        // If the cached `Class_` resolver's `$this->scope` is clobbered by
        // the inner traversal, the outer `onExit`'s subsequent reads of
        // `$this->scope` (or downstream consumers) misbehave and the
        // relation methods don't get attached to the correct ClassResult.
        $analyzer = app(Analyzer::class);
        $result = $analyzer->analyzeClass(User::class)->result();

        expect($result)->toBeInstanceOf(ClassResult::class);
        expect($result->name())->toBe(User::class);

        // `posts` is added by ModelAnalyzer *after* attribute resolution
        // (which is what triggers the recursion). Missing → pool is leaking
        // inner scope back into the outer Class_ resolver.
        expect($result->hasMethod('posts'))->toBeTrue();
    });
});
