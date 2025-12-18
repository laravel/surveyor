<?php

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;

uses()->group('cache');

beforeEach(function () {
    AnalyzedCache::clear();
    resetCacheDirectory();
});

afterEach(function () {
    AnalyzedCache::clear();
    resetCacheDirectory();
});

function resetCacheDirectory(): void
{
    $reflection = new ReflectionClass(AnalyzedCache::class);

    $dirProp = $reflection->getProperty('cacheDirectory');
    $dirProp->setValue(null, null);

    $persistProp = $reflection->getProperty('persistToDisk');
    $persistProp->setValue(null, false);

    $depsProp = $reflection->getProperty('dependencies');
    $depsProp->setValue(null, []);
}

function createCacheDir(): string
{
    $dir = sys_get_temp_dir().'/surveyor-test-cache-'.uniqid();
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function cleanupCacheDir(string $dir): void
{
    if (is_dir($dir)) {
        foreach (glob($dir.'/*.cache') as $file) {
            unlink($file);
        }
        rmdir($dir);
    }
}

describe('memory caching', function () {
    it('stores and retrieves analyzed scope from memory', function () {
        $fixture = createTestClassFixture('TestClass', 'public function test() { return "hello"; }');

        $scope = new Scope;
        $scope->setPath($fixture);

        AnalyzedCache::add($fixture, $scope);

        $cached = AnalyzedCache::get($fixture);
        expect($cached)->toBe($scope);

        unlink($fixture);
    });

    it('returns null for non-existent files', function () {
        $nonExistent = '/path/to/nonexistent/file.php';
        expect(AnalyzedCache::get($nonExistent))->toBeNull();
    });

    it('invalidates cache when file modification time changes', function () {
        $fixture = createTestClassFixture('TestClass', 'public function test() {}');

        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        expect(AnalyzedCache::get($fixture))->not->toBeNull();

        sleep(1);
        file_put_contents($fixture, "<?php\nclass TestClass { public function modified() {} }");

        expect(AnalyzedCache::get($fixture))->toBeNull();

        unlink($fixture);
    });

    it('can manually invalidate cached entries', function () {
        $fixture = createTestClassFixture('TestClass', 'public function test() {}');

        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        expect(AnalyzedCache::get($fixture))->not->toBeNull();

        AnalyzedCache::invalidate($fixture);

        expect(AnalyzedCache::get($fixture))->toBeNull();

        unlink($fixture);
    });

    it('clears all memory cache entries', function () {
        $fixture1 = createTestClassFixture('TestClass1', 'public function test1() {}');
        $fixture2 = createTestClassFixture('TestClass2', 'public function test2() {}');

        $scope1 = new Scope;
        $scope1->setPath($fixture1);
        $scope2 = new Scope;
        $scope2->setPath($fixture2);

        AnalyzedCache::add($fixture1, $scope1);
        AnalyzedCache::add($fixture2, $scope2);

        expect(AnalyzedCache::get($fixture1))->not->toBeNull();
        expect(AnalyzedCache::get($fixture2))->not->toBeNull();

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($fixture1))->toBeNull();
        expect(AnalyzedCache::get($fixture2))->toBeNull();

        unlink($fixture1);
        unlink($fixture2);
    });
});

describe('disk caching', function () {
    it('creates cache directory when setting directory', function () {
        $dir = sys_get_temp_dir().'/surveyor-cache-test-'.uniqid();

        expect(is_dir($dir))->toBeFalse();

        AnalyzedCache::setCacheDirectory($dir);

        expect(is_dir($dir))->toBeTrue();

        rmdir($dir);
    });

    it('throws exception when enabling without setting directory', function () {
        expect(fn () => AnalyzedCache::enable())
            ->toThrow(RuntimeException::class, 'Cache directory must be set');
    });

    it('enables disk cache with convenience method', function () {
        $dir = createCacheDir();

        AnalyzedCache::enableDiskCache($dir);

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        $cacheFiles = glob($dir.'/*.cache');
        expect($cacheFiles)->toHaveCount(1);

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('persists cache to disk and loads from disk', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        AnalyzedCache::clearMemory();

        $cached = AnalyzedCache::get($fixture);
        expect($cached)->not->toBeNull();
        expect($cached)->toBeInstanceOf(Scope::class);

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('invalidates disk cache when file is modified', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        $cacheFiles = glob($dir.'/*.cache');
        expect($cacheFiles)->toHaveCount(1);

        sleep(1);
        file_put_contents($fixture, "<?php\nclass TestClass { public function modified() {} }");

        AnalyzedCache::clearMemory();

        $cached = AnalyzedCache::get($fixture);
        expect($cached)->toBeNull();

        $cacheFiles = glob($dir.'/*.cache');
        expect($cacheFiles)->toHaveCount(0);

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('can disable and re-enable disk caching', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $fixture1 = createTestClassFixture('TestClass1', 'public function test() {}');
        $scope1 = new Scope;
        $scope1->setPath($fixture1);
        AnalyzedCache::add($fixture1, $scope1);

        expect(glob($dir.'/*.cache'))->toHaveCount(1);

        AnalyzedCache::disable();

        $fixture2 = createTestClassFixture('TestClass2', 'public function test() {}');
        $scope2 = new Scope;
        $scope2->setPath($fixture2);
        AnalyzedCache::add($fixture2, $scope2);

        expect(glob($dir.'/*.cache'))->toHaveCount(1);

        unlink($fixture1);
        unlink($fixture2);
        cleanupCacheDir($dir);
    });

    it('clears both memory and disk cache', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        expect(glob($dir.'/*.cache'))->toHaveCount(1);

        AnalyzedCache::clear();

        expect(glob($dir.'/*.cache'))->toHaveCount(0);

        unlink($fixture);
        cleanupCacheDir($dir);
    });
});

describe('in-progress tracking', function () {
    it('tracks files being analyzed', function () {
        $path = '/some/file.php';

        expect(AnalyzedCache::isInProgress($path))->toBeFalse();

        AnalyzedCache::inProgress($path);

        expect(AnalyzedCache::isInProgress($path))->toBeTrue();
    });

    it('clears in-progress when adding to cache', function () {
        $fixture = createTestClassFixture('TestClass', 'public function test() {}');

        AnalyzedCache::inProgress($fixture);
        expect(AnalyzedCache::isInProgress($fixture))->toBeTrue();

        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        expect(AnalyzedCache::isInProgress($fixture))->toBeFalse();

        unlink($fixture);
    });

    it('clears in-progress when clearing memory', function () {
        $path = '/some/file.php';

        AnalyzedCache::inProgress($path);
        expect(AnalyzedCache::isInProgress($path))->toBeTrue();

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::isInProgress($path))->toBeFalse();
    });
});

describe('dependency tracking', function () {
    it('tracks dependencies', function () {
        AnalyzedCache::addDependency('/path/to/dep1.php');
        AnalyzedCache::addDependency('/path/to/dep2.php');

        $reflection = new ReflectionClass(AnalyzedCache::class);
        $depsProp = $reflection->getProperty('dependencies');

        $deps = $depsProp->getValue();
        expect($deps)->toContain('/path/to/dep1.php');
        expect($deps)->toContain('/path/to/dep2.php');
    });

    it('stores dependencies when persisting to disk', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $mainFixture = createTestClassFixture('MainClass', 'public function main() {}');
        $depFixture = createTestClassFixture('DepClass', 'public function dep() {}');

        AnalyzedCache::addDependency($depFixture);

        $scope = new Scope;
        $scope->setPath($mainFixture);
        AnalyzedCache::add($mainFixture, $scope);

        $cacheFiles = glob($dir.'/*.cache');
        expect($cacheFiles)->toHaveCount(1);

        $cacheData = unserialize(file_get_contents($cacheFiles[0]));
        expect($cacheData)->toHaveKey('dependencies');
        expect(count($cacheData['dependencies']))->toBeGreaterThanOrEqual(1);

        $depPaths = array_column($cacheData['dependencies'], 'path');
        expect($depPaths)->toContain($depFixture);

        unlink($mainFixture);
        unlink($depFixture);
        cleanupCacheDir($dir);
    });

    it('invalidates cache when dependency file changes', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $mainFixture = createTestClassFixture('MainClass', 'public function main() {}');
        $depFixture = createTestClassFixture('DepClass', 'public function dep() {}');

        AnalyzedCache::addDependency($depFixture);

        $scope = new Scope;
        $scope->setPath($mainFixture);
        AnalyzedCache::add($mainFixture, $scope);

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($mainFixture))->not->toBeNull();

        sleep(1);
        file_put_contents($depFixture, "<?php\nclass DepClass { public function modified() {} }");

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($mainFixture))->toBeNull();

        unlink($mainFixture);
        unlink($depFixture);
        cleanupCacheDir($dir);
    });
});

describe('integration with Analyzer', function () {
    it('caches analyzed files through Analyzer', function () {
        $fixture = createTestClassFixture('AnalyzerTestClass', 'public function test() { return "hello"; }');

        $analyzer = app(Analyzer::class);

        $result1 = $analyzer->analyze($fixture);
        $scope1 = $result1->analyzed();

        $result2 = $analyzer->analyze($fixture);
        $scope2 = $result2->analyzed();

        expect($scope1)->toBe($scope2);

        unlink($fixture);
    });

    it('re-analyzes when file changes', function () {
        $fixture = createTestClassFixture('AnalyzerTestClass', 'public function test() { return "hello"; }');

        $analyzer = app(Analyzer::class);

        $result1 = $analyzer->analyze($fixture);
        $scope1 = $result1->analyzed();

        sleep(1);
        file_put_contents($fixture, "<?php\nclass AnalyzerTestClass { public function modified() {} }");

        $result2 = $analyzer->analyze($fixture);
        $scope2 = $result2->analyzed();

        expect($scope1)->not->toBe($scope2);

        unlink($fixture);
    });
});
