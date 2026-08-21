<?php

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();

    app()->forgetInstance(Analyzer::class);
});

afterEach(function () {
    AnalyzedCache::clear();
});

function determinismFixture(string $name): string
{
    return dirname(__DIR__, 3).'/workbench/app/Determinism/'.$name;
}

/**
 * The part of an analyzed file a dependent can observe: its methods, their
 * parameters and what they return.
 */
function surfaceOf(?Scope $scope): array
{
    $result = $scope?->result();

    if ($result === null) {
        return [];
    }

    $describe = fn (?TypeContract $type) => $type === null
        ? '-'
        : $type::class.':'.$type->id().($type->isNullable() ? '|null' : '');

    $lines = [];

    foreach ($result->publicMethods() as $name => $method) {
        $parameters = [];

        foreach ($method->parameters() as $parameter => $type) {
            $parameters[] = $parameter.' '.$describe($type);
        }

        $lines[] = $name.'('.implode(', ', $parameters).'): '.$describe($method->returnType());
    }

    sort($lines);

    return $lines;
}

it('resolves a file\'s own imports after analyzing another file', function () {
    $analyzed = app(Analyzer::class)->analyze(determinismFixture('Outer.php'));

    $stamp = $analyzed->result()->getMethod('stamp');

    expect($stamp->parameters()['marker']->id())->toBe('App\\Determinism\\Types\\Marker');
    expect($stamp->returnType()->id())->toBe('App\\Determinism\\Types\\Marker');
});

it('gives the same surface whichever file is analyzed first', function () {
    $analyzer = app(Analyzer::class);

    $first = surfaceOf($analyzer->analyze(determinismFixture('Outer.php'))->analyzed());

    expect($first)->not->toBeEmpty();

    AnalyzedCache::clear();
    app()->forgetInstance(Analyzer::class);

    $analyzer = app(Analyzer::class);
    $analyzer->analyze(determinismFixture('Nested.php'));

    $second = surfaceOf($analyzer->analyze(determinismFixture('Outer.php'))->analyzed());

    expect($second)->toBe($first);
});

it('settles a cycle member against the finished analysis of the other member', function () {
    $analyzer = app(Analyzer::class);

    // Alpha is analyzed from inside Beta, so it asks what Beta::make() returns
    // while Beta is still open.
    $analyzer->analyze(determinismFixture('Cycle/Beta.php'));

    $alpha = $analyzer->analyze(determinismFixture('Cycle/Alpha.php'))->result();

    expect($alpha->getMethod('beta')->returnType()->id())->toBe('App\\Determinism\\Cycle\\Beta');
});
