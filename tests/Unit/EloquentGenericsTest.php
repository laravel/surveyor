<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\MixedType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

function eloquentGenericsResult()
{
    return app(Analyzer::class)->analyze(createPhpFixture('
namespace App;

use App\\Models\\User;

class EloquentGenericsFixture
{
    public function staticFirstOrFail() { return User::firstOrFail(); }
    public function staticFindOrFail() { return User::findOrFail(1); }
    public function staticCreate() { return User::create([]); }
    public function staticFirst() { return User::first(); }
    public function staticFind() { return User::find(1); }
    public function staticWhereFirst() { return User::where("id", 1)->first(); }
    public function staticLatestFirst() { return User::latest()->first(); }
    public function collectionFirst() { return User::all()->first(); }
    public function staticPaginate() { return User::paginate(); }
}
'))->result();
}

it('binds the called model when a static call forwards to the query builder', function (string $method, bool $nullable) {
    $type = eloquentGenericsResult()->getMethod($method)->returnType();

    expect($type)->toBeInstanceOf(ClassType::class);
    expect($type->value)->toBe('App\Models\User');
    expect($type->isNullable())->toBe($nullable);
})->with([
    ['staticFirstOrFail', false],
    ['staticFindOrFail', false],
    ['staticCreate', false],
    ['staticFirst', true],
    ['staticFind', true],
    ['staticWhereFirst', true],
    ['staticLatestFirst', true],
    ['collectionFirst', true],
]);

it('binds the called model into a paginator', function () {
    $type = eloquentGenericsResult()->getMethod('staticPaginate')->returnType();

    expect($type->value)->toBe('Illuminate\Pagination\LengthAwarePaginator');
    expect(array_values($type->genericTypes())[1]->value)->toBe('App\Models\User');
});

it('never turns an unbound template name into a string literal', function () {
    $type = app(Analyzer::class)->analyze(createPhpFixture('
namespace App;

class UnboundTemplateFixture
{
    public function mapped() { return collect([1, 2])->map(fn ($n) => $n * 2); }
}
'))->result()->getMethod('mapped')->returnType();

    expect($type->value)->toBe('Illuminate\Support\Collection');
    expect(array_values($type->genericTypes())[1])->toBeInstanceOf(MixedType::class);
});
