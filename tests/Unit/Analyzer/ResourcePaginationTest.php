<?php

use App\Http\Resources\PlainLabelResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Entities\ResourceResponse;

beforeEach(fn () => AnalyzedCache::clear());
afterEach(fn () => AnalyzedCache::clear());

it('retains pagination from resource collection inputs', function (string $method, array $keys, array $absent) {
    $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\PlainLabelResource;
use App\\Models\\Tag;

class PaginatedController
{
    public function index()
    {
        return PlainLabelResource::collection(Tag::query()->'.$method.'(10));
    }
}');

    try {
        $type = app(Analyzer::class)->analyze($fixture)->result()->getMethod('index')->returnType();

        expect($type)->toBeInstanceOf(ResourceResponse::class)
            ->and($type->pagination)->not->toBeNull()
            ->and($type->pagination->value['links']->keys())->toBe(['first', 'last', 'prev', 'next']);

        foreach ($keys as $key) {
            expect($type->pagination->value['meta']->value)->toHaveKey($key);
        }

        foreach ($absent as $key) {
            expect($type->pagination->value['meta']->value)->not->toHaveKey($key);
        }
    } finally {
        unlink($fixture);
    }
})->with([
    ['paginate', ['current_page', 'last_page', 'total', 'links'], ['next_cursor']],
    ['simplePaginate', ['current_page', 'from', 'to'], ['last_page', 'total', 'links', 'next_cursor']],
    ['cursorPaginate', ['next_cursor', 'prev_cursor'], ['current_page', 'last_page', 'total', 'from', 'to']],
]);

it('follows a collection that customizes its pagination information', function (string $collection, ?array $keys) {
    $fixture = createPhpFixture('
namespace App\\Test;

use App\\Http\\Resources\\'.$collection.';
use App\\Models\\Tag;

class CustomPaginationController
{
    public function index()
    {
        return new '.$collection.'(Tag::query()->paginate(10));
    }
}');

    try {
        $type = app(Analyzer::class)->analyze($fixture)->result()->getMethod('index')->returnType();

        expect($type)->toBeInstanceOf(ResourceResponse::class)
            ->and($type->pagination?->keys())->toBe($keys);
    } finally {
        unlink($fixture);
    }
})->with([
    'its own shape' => ['CustomPaginationCollection', ['pagination']],
    'the default it builds on' => ['AdjustedPaginationCollection', ['links', 'meta']],
]);

it('describes no pagination for a runtime paginationInformation macro', function () {
    ResourceCollection::macro('paginationInformation', fn ($request, $paginated, $default) => $default);

    try {
        $type = app(ResourceAnalyzer::class)->buildResourceResponse(
            PlainLabelResource::class,
            isCollection: true,
            resource: new ClassType(LengthAwarePaginator::class),
        );

        expect($type->pagination)->toBeNull();
    } finally {
        ResourceCollection::flushMacros();
    }
});
