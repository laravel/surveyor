<?php

use App\Macros\SurveyorMacros;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\StringType;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

it('resolves the return type of a registered macro', function () {
    SurveyorMacros::register();

    $fixture = createPhpFixture('
namespace App;

use Illuminate\Support\Str;

class MacroSubject
{
    public function test()
    {
        return Str::surveyorShout("hi");
    }
}
');

    $returnType = app(Analyzer::class)
        ->analyze($fixture)
        ->result()
        ->getMethod('test')
        ->returnType();

    expect($returnType)->toBeInstanceOf(StringType::class);
});
