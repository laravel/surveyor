<?php

use Illuminate\Validation\Rules\Enum;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
});

afterEach(function () {
    AnalyzedCache::clear();
});

it('keeps rule objects that say something about the field type', function () {
    require_once createPhpFixture('
namespace App\\Http\\Requests;

enum RuleObjectStatus: string
{
    case Draft = "draft";
}
');

    $fixture = createPhpFixture('
namespace App\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;
use Illuminate\\Validation\\Rule;
use Illuminate\\Validation\\Rules\\File;

class RuleObjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "status" => ["required", Rule::enum(RuleObjectStatus::class)],
            "size" => ["required", Rule::in(["sm", "lg"])],
            "avatar" => ["nullable", File::image()],
            "name" => ["required", Rule::unique("users", "name")],
        ];
    }
}
');

    $rules = app(Analyzer::class)->analyze($fixture)->result()->getMethod('rules')->validationRules();

    expect($rules['status'][1][0])->toBeInstanceOf(Enum::class);
    expect($rules['size'][1])->toBe(['In', ['sm', 'lg']]);
    expect($rules['avatar'][1][0])->toBeIn(['File', 'Image']);
    expect($rules['name'])->toHaveCount(1);
});
