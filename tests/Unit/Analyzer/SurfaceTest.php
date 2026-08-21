<?php

use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\Surface;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();

    app()->forgetInstance(Analyzer::class);
});

afterEach(function () {
    AnalyzedCache::clear();
});

/**
 * Analyze a file from an empty cache and hash what a dependent could see.
 */
function surfaceHashOf(string $path): string
{
    AnalyzedCache::clear();

    app()->forgetInstance(Analyzer::class);

    return Surface::hash(app(Analyzer::class)->analyze($path)->analyzed());
}

function rewriteFixture(string $path, string $content): void
{
    file_put_contents($path, "<?php\n\n".$content);
}

const SURFACE_SUBJECT = '
namespace App\Surface;

class Subject
{
    public const KIND = "subject";

    public string $name = "subject";

    public function label(string $prefix): string
    {
        return $prefix.$this->name;
    }
}';

it('hashes the same source to the same surface', function () {
    $fixture = createPhpFixture(SURFACE_SUBJECT);

    expect(surfaceHashOf($fixture))->toBe(surfaceHashOf($fixture));

    unlink($fixture);
});

it('leaves the surface alone when only a method body changes', function () {
    $fixture = createPhpFixture(SURFACE_SUBJECT);

    $before = surfaceHashOf($fixture);

    rewriteFixture($fixture, str_replace(
        'return $prefix.$this->name;',
        'return $prefix.strtoupper($this->name);',
        SURFACE_SUBJECT,
    ));

    expect(surfaceHashOf($fixture))->toBe($before);

    unlink($fixture);
});

it('moves the surface when a public method is added', function () {
    $fixture = createPhpFixture(SURFACE_SUBJECT);

    $before = surfaceHashOf($fixture);

    rewriteFixture($fixture, str_replace(
        '    public function label(',
        "    public function extra(): int\n    {\n        return 1;\n    }\n\n    public function label(",
        SURFACE_SUBJECT,
    ));

    expect(surfaceHashOf($fixture))->not->toBe($before);

    unlink($fixture);
});

it('moves the surface when a return type changes', function () {
    $fixture = createPhpFixture(SURFACE_SUBJECT);

    $before = surfaceHashOf($fixture);

    rewriteFixture($fixture, str_replace(
        'public function label(string $prefix): string',
        'public function label(string $prefix): ?string',
        SURFACE_SUBJECT,
    ));

    expect(surfaceHashOf($fixture))->not->toBe($before);

    unlink($fixture);
});

it('moves the surface when an import changes what a name means', function () {
    $body = '
namespace App\\Surface;

use App\\Surface\\%s\\Marker;

class Importer
{
    public function stamp(Marker $marker): Marker
    {
        return $marker;
    }
}';

    $first = createPhpFixture(sprintf($body, 'One'));
    $second = createPhpFixture(sprintf($body, 'Two'));

    expect(surfaceHashOf($first))->not->toBe(surfaceHashOf($second));

    unlink($first);
    unlink($second);
});

it('separates two files that record nothing observable', function () {
    $first = createPhpFixture('
namespace App\Surface;

enum FirstKind: string
{
    case ALPHA = "alpha";
}');

    $second = createPhpFixture('
namespace App\Surface;

enum SecondKind: string
{
    case ALPHA = "alpha";
}');

    expect(surfaceHashOf($first))->not->toBe(surfaceHashOf($second));

    unlink($first);
    unlink($second);
});

it('moves the surface of an enum when a case changes', function () {
    $fixture = createPhpFixture('
namespace App\Surface;

enum Kind: string
{
    case ALPHA = "alpha";
}');

    $before = surfaceHashOf($fixture);

    rewriteFixture($fixture, '
namespace App\Surface;

enum Kind: string
{
    case ALPHA = "alpha";
    case BETA = "beta";
}');

    expect(surfaceHashOf($fixture))->not->toBe($before);

    unlink($fixture);
});
