<?php

declare(strict_types=1);

use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\SurveyorServiceProvider;
use Orchestra\Testbench\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)
    ->beforeEach(function () {
        $this->app->register(SurveyorServiceProvider::class);
    })
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the amount of code duplication.
|
*/

function analyzeFile(string $path)
{
    return app(Analyzer::class)->analyze(
        dirname(__DIR__, 1).'/workbench/'.$path,
    );
}

/**
 * Create a simple PHP file fixture for testing
 */
function createPhpFixture(string $content): string
{
    $tempFile = tempnam(sys_get_temp_dir(), 'test_fixture_');
    file_put_contents($tempFile, "<?php\n\n".$content);

    return $tempFile;
}

/**
 * Create a test class fixture
 */
function createTestClassFixture(string $className, string $content = ''): string
{
    return createPhpFixture("
class {$className}
{
    {$content}
}");
}

/**
 * Get the parser instance for testing
 */
function getParser(): Laravel\Surveyor\Parser\Parser
{
    return app(Laravel\Surveyor\Parser\Parser::class);
}

/**
 * Parse PHP code and return the Scope results
 */
function parseCode(string $code, string $path = '/tmp/test.php'): array
{
    $parser = getParser();

    return $parser->parse("<?php\n\n".$code, $path);
}
