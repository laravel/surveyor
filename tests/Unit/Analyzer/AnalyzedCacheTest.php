<?php

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\Surface;

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
    // Nothing else resets this, so a frozen test would leak into every test after it.
    AnalyzedCache::freezeFileTimes(false);

    $reflection = new ReflectionClass(AnalyzedCache::class);

    $dirProp = $reflection->getProperty('cacheDirectory');
    $dirProp->setValue(null, null);

    $persistProp = $reflection->getProperty('persistToDisk');
    $persistProp->setValue(null, false);

    foreach (['dependencies', 'records', 'current', 'surfaces'] as $property) {
        $reflection->getProperty($property)->setValue(null, []);
    }

    // Registered by the analyzer's constructor, so it outlives the test that
    // built one and would decide validity for every test after it.
    AnalyzedCache::resolveSurfaceUsing(null);

    // A test that fails mid-analysis leaves a frame open, which would then
    // attach its dependencies to whatever runs next.
    foreach (['frames', 'framePaths', 'frameTainted', 'deferred'] as $property) {
        $reflection->getProperty($property)->setValue(null, []);
    }

    $reflection->getProperty('cycleFloor')->setValue(null, null);

    $keyProp = $reflection->getProperty('key');
    $keyProp->setValue(null, null);
}

/**
 * @return list<string>
 */
function dependenciesFor(string $path): array
{
    $dependencies = (new ReflectionProperty(AnalyzedCache::class, 'dependencies'))->getValue();

    return array_keys($dependencies[$path] ?? []);
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
        foreach ([...glob($dir.'/*.cache'), ...glob($dir.'/*.record')] as $file) {
            unlink($file);
        }

        $gitignore = $dir.'/.gitignore';

        if (file_exists($gitignore)) {
            unlink($gitignore);
        }

        rmdir($dir);
    }
}

function getCacheFilePayload(string $content): string
{
    if (strlen($content) > 65 && ctype_xdigit(substr($content, 0, 64)) && $content[64] === ':') {
        return substr($content, 65);
    }

    return $content;
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

describe('frozen file times', function () {
    it('ignores modifications made while file times are frozen', function () {
        AnalyzedCache::freezeFileTimes();

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');

        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        touch($fixture, time() + 10);

        expect(AnalyzedCache::get($fixture))->not->toBeNull();

        unlink($fixture);
    });

    it('sees modifications again once file times are unfrozen', function () {
        AnalyzedCache::freezeFileTimes();

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');

        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        touch($fixture, time() + 10);

        expect(AnalyzedCache::get($fixture))->not->toBeNull();

        AnalyzedCache::freezeFileTimes(false);

        expect(AnalyzedCache::get($fixture))->toBeNull();

        unlink($fixture);
    });

    it('still invalidates on modification when not frozen', function () {
        $fixture = createTestClassFixture('TestClass', 'public function test() {}');

        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        touch($fixture, time() + 10);

        expect(AnalyzedCache::get($fixture))->toBeNull();

        unlink($fixture);
    });
});

describe('disk caching', function () {
    it('creates cache directory when setting directory', function () {
        $dir = sys_get_temp_dir().'/surveyor-cache-test-'.uniqid();

        expect(is_dir($dir))->toBeFalse();

        AnalyzedCache::setCacheDirectory($dir);

        expect(is_dir($dir))->toBeTrue();

        cleanupCacheDir($dir);
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

        $content = file_get_contents($cacheFiles[0]);
        $cacheData = unserialize(getCacheFilePayload($content));
        expect($cacheData)->toBeArray();

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

    it('treats a corrupted cache file as a miss and deletes it', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        $cacheFiles = glob($dir.'/*.cache');
        expect($cacheFiles)->toHaveCount(1);
        $cacheFile = $cacheFiles[0];

        $content = file_get_contents($cacheFile);
        file_put_contents($cacheFile, substr($content, 0, intdiv(strlen($content), 2)).'garbage');

        AnalyzedCache::clearMemory();

        $cached = AnalyzedCache::get($fixture);
        expect($cached)->toBeNull();
        expect(file_exists($cacheFile))->toBeFalse();

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('treats a signed cache file with corrupted payload as a miss and deletes it', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);
        AnalyzedCache::setKey('base-secret-key');

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        $cacheFiles = glob($dir.'/*.cache');
        expect($cacheFiles)->toHaveCount(1);
        $cacheFile = $cacheFiles[0];

        $content = file_get_contents($cacheFile);
        [, $serialized] = explode(':', $content, 2);
        $truncated = substr($serialized, 0, intdiv(strlen($serialized), 2));
        $signature = hash_hmac('sha256', $truncated, 'base-secret-key');
        file_put_contents($cacheFile, $signature.':'.$truncated);

        AnalyzedCache::clearMemory();

        $cached = AnalyzedCache::get($fixture);
        expect($cached)->toBeNull();
        expect(file_exists($cacheFile))->toBeFalse();

        unlink($fixture);
        cleanupCacheDir($dir);
    });
});

describe('signed cache', function () {
    it('signs and verifies cache data when key is set', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);
        AnalyzedCache::setKey('base-secret-key');

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        AnalyzedCache::clearMemory();

        $cached = AnalyzedCache::get($fixture);
        expect($cached)->not->toBeNull()
            ->and($cached->path())->toBe($fixture);

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('rejects cache with invalid signature and deletes file', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);
        AnalyzedCache::setKey('base-secret-key');

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        $cacheFiles = glob($dir.'/*.cache');
        expect($cacheFiles)->toHaveCount(1);
        $cacheFile = $cacheFiles[0];

        $content = file_get_contents($cacheFile);
        $parts = explode(':', $content, 2);
        $tampered = $parts[0].':'.$parts[1].'tampered';
        file_put_contents($cacheFile, $tampered);

        AnalyzedCache::clearMemory();

        $cached = AnalyzedCache::get($fixture);
        expect($cached)->toBeNull();
        expect(file_exists($cacheFile))->toBeFalse();

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('rejects cache when key changes', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);
        AnalyzedCache::setKey('base-secret-key');

        $fixture = createTestClassFixture('TestClass', 'public function test() {}');
        $scope = new Scope;
        $scope->setPath($fixture);
        AnalyzedCache::add($fixture, $scope);

        AnalyzedCache::clearMemory();
        AnalyzedCache::setKey('different-secret-key');

        $cached = AnalyzedCache::get($fixture);
        expect($cached)->toBeNull();

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
    it('tracks dependencies against the file being analyzed', function () {
        AnalyzedCache::beginAnalysis('/path/to/main.php');
        AnalyzedCache::addDependency('/path/to/dep1.php');
        AnalyzedCache::addDependency('/path/to/dep2.php');
        AnalyzedCache::endAnalysis('/path/to/main.php');

        expect(dependenciesFor('/path/to/main.php'))
            ->toContain('/path/to/dep1.php')
            ->toContain('/path/to/dep2.php');
    });

    it('does not record a dependency twice', function () {
        AnalyzedCache::beginAnalysis('/path/to/main.php');
        AnalyzedCache::addDependency('/path/to/dep1.php');
        AnalyzedCache::addDependency('/path/to/dep1.php');
        AnalyzedCache::endAnalysis('/path/to/main.php');

        expect(dependenciesFor('/path/to/main.php'))->toBe(['/path/to/dep1.php']);
    });

    it('does not record a dependency against an unrelated file', function () {
        AnalyzedCache::beginAnalysis('/path/to/first.php');
        AnalyzedCache::addDependency('/path/to/dep1.php');
        AnalyzedCache::endAnalysis('/path/to/first.php');

        AnalyzedCache::beginAnalysis('/path/to/second.php');
        AnalyzedCache::addDependency('/path/to/dep2.php');
        AnalyzedCache::endAnalysis('/path/to/second.php');

        expect(dependenciesFor('/path/to/second.php'))->toBe(['/path/to/dep2.php']);
    });

    it('records only the files a file reached itself', function () {
        AnalyzedCache::beginAnalysis('/path/to/a.php');
        AnalyzedCache::addDependency('/path/to/b.php');

        AnalyzedCache::beginAnalysis('/path/to/b.php');
        AnalyzedCache::addDependency('/path/to/c.php');
        AnalyzedCache::endAnalysis('/path/to/b.php');

        AnalyzedCache::endAnalysis('/path/to/a.php');

        expect(dependenciesFor('/path/to/a.php'))->toBe(['/path/to/b.php']);
        expect(dependenciesFor('/path/to/b.php'))->toBe(['/path/to/c.php']);
    });

    it('does not record a file as depending on itself', function () {
        AnalyzedCache::beginAnalysis('/path/to/a.php');
        AnalyzedCache::addDependency('/path/to/a.php');
        AnalyzedCache::endAnalysis('/path/to/a.php');

        expect(dependenciesFor('/path/to/a.php'))->toBe([]);
    });

    it('stores dependencies when persisting to disk', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $mainFixture = createTestClassFixture('MainClass', 'public function main() {}');
        $depFixture = createTestClassFixture('DepClass', 'public function dep() {}');

        AnalyzedCache::beginAnalysis($mainFixture);
        AnalyzedCache::addDependency($depFixture);

        $scope = new Scope;
        $scope->setPath($mainFixture);
        AnalyzedCache::add($mainFixture, $scope);

        AnalyzedCache::endAnalysis($mainFixture);

        $cacheFiles = glob($dir.'/*.cache');
        expect($cacheFiles)->toHaveCount(1);

        $recordFiles = glob($dir.'/*.record');
        expect($recordFiles)->toHaveCount(1);

        $record = unserialize(getCacheFilePayload(file_get_contents($recordFiles[0])));
        expect($record)->toHaveKey('dependencies');

        $depPaths = array_column($record['dependencies'], 'path');
        expect($depPaths)->toContain($depFixture);

        unlink($mainFixture);
        unlink($depFixture);
        cleanupCacheDir($dir);
    });

    it('invalidates cache when dependency file is deleted', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $mainFixture = createTestClassFixture('MainClass', 'public function main() {}');
        $depFixture = createTestClassFixture('DepClass', 'public function dep() {}');

        AnalyzedCache::beginAnalysis($mainFixture);
        AnalyzedCache::addDependency($depFixture);

        $scope = new Scope;
        $scope->setPath($mainFixture);
        AnalyzedCache::add($mainFixture, $scope);

        AnalyzedCache::endAnalysis($mainFixture);

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($mainFixture))->not->toBeNull();

        unlink($depFixture);

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($mainFixture))->toBeNull();

        unlink($mainFixture);
        cleanupCacheDir($dir);
    });

    it('invalidates cache when dependency file changes', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $mainFixture = createTestClassFixture('MainClass', 'public function main() {}');
        $depFixture = createTestClassFixture('DepClass', 'public function dep() {}');

        AnalyzedCache::beginAnalysis($mainFixture);
        AnalyzedCache::addDependency($depFixture);

        $scope = new Scope;
        $scope->setPath($mainFixture);
        AnalyzedCache::add($mainFixture, $scope);

        AnalyzedCache::endAnalysis($mainFixture);

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

    it('invalidates cache when an indirect dependency changes', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $a = createTestClassFixture('AClass', 'public function a() {}');
        $b = createTestClassFixture('BClass', 'public function b() {}');
        $c = createTestClassFixture('CClass', 'public function c() {}');

        // A uses B, B uses C. Nothing links A to C directly.
        AnalyzedCache::beginAnalysis($a);
        AnalyzedCache::addDependency($b);

        AnalyzedCache::beginAnalysis($b);
        AnalyzedCache::addDependency($c);
        AnalyzedCache::add($b, tap(new Scope, fn ($scope) => $scope->setPath($b)));
        AnalyzedCache::endAnalysis($b);

        AnalyzedCache::add($a, tap(new Scope, fn ($scope) => $scope->setPath($a)));
        AnalyzedCache::endAnalysis($a);

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($a))->not->toBeNull();

        sleep(1);
        file_put_contents($c, "<?php\nclass CClass { public function modified() {} }");

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($a))->toBeNull();

        unlink($a);
        unlink($b);
        unlink($c);
        cleanupCacheDir($dir);
    });

    it('invalidates every member of a cycle when one of them changes', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $a = createTestClassFixture('CycleA', 'public function a() {}');
        $b = createTestClassFixture('CycleB', 'public function b() {}');

        // A uses B, and B uses A again while A is still being analyzed.
        AnalyzedCache::beginAnalysis($a);
        AnalyzedCache::addDependency($b);

        AnalyzedCache::beginAnalysis($b);
        AnalyzedCache::addDependency($a);
        AnalyzedCache::noteCycle($a);
        AnalyzedCache::add($b, tap(new Scope, fn ($scope) => $scope->setPath($b)));
        AnalyzedCache::endAnalysis($b);

        AnalyzedCache::add($a, tap(new Scope, fn ($scope) => $scope->setPath($a)));
        AnalyzedCache::endAnalysis($a);

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($a))->not->toBeNull();
        expect(AnalyzedCache::get($b))->not->toBeNull();

        // B is cached against A, even though A is the file that came first.
        expect(dependenciesFor($b))->toContain($a);

        sleep(1);
        file_put_contents($a, "<?php\nclass CycleA { public function modified() {} }");

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($b))->toBeNull();

        unlink($a);
        unlink($b);
        cleanupCacheDir($dir);
    });

    it('does not record dependencies against unrelated files analyzed later', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $first = createTestClassFixture('FirstClass', 'public function first() {}');
        $dep = createTestClassFixture('SharedDep', 'public function dep() {}');
        $later = createTestClassFixture('LaterClass', 'public function later() {}');

        AnalyzedCache::beginAnalysis($first);
        AnalyzedCache::addDependency($dep);
        AnalyzedCache::add($first, tap(new Scope, fn ($scope) => $scope->setPath($first)));
        AnalyzedCache::endAnalysis($first);

        AnalyzedCache::beginAnalysis($later);
        AnalyzedCache::add($later, tap(new Scope, fn ($scope) => $scope->setPath($later)));
        AnalyzedCache::endAnalysis($later);

        AnalyzedCache::clearMemory();

        sleep(1);
        file_put_contents($first, "<?php\nclass FirstClass { public function modified() {} }");

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::get($first))->toBeNull();
        expect(AnalyzedCache::get($later))->not->toBeNull();

        unlink($first);
        unlink($dep);
        unlink($later);
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

describe('surface hashes', function () {
    it('writes a record alongside a persisted entry', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $fixture = createTestClassFixture('SurfaceSubject', 'public function test(): string { return "x"; }');
        $analyzed = app(Analyzer::class)->analyze($fixture);

        expect(glob($dir.'/*.record'))->toHaveCount(1);
        expect(AnalyzedCache::surfaceHash($fixture))->toBe(Surface::hash($analyzed->analyzed()));

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('reads a surface back without loading the entry', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $fixture = createTestClassFixture('SurfaceSubject', 'public function test(): string { return "x"; }');
        app(Analyzer::class)->analyze($fixture);

        $expected = AnalyzedCache::surfaceHash($fixture);

        AnalyzedCache::clearMemory();

        expect(AnalyzedCache::surfaceHash($fixture))->toBe($expected);

        // Reading the surface must not pull the entry into memory.
        $cached = (new ReflectionProperty(AnalyzedCache::class, 'cached'))->getValue();
        expect($cached)->toBe([]);

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('answers from memory when nothing is persisted', function () {
        $fixture = createTestClassFixture('SurfaceSubject', 'public function test(): string { return "x"; }');
        $analyzed = app(Analyzer::class)->analyze($fixture);

        expect(AnalyzedCache::surfaceHash($fixture))->toBe(Surface::hash($analyzed->analyzed()));

        unlink($fixture);
    });

    it('forgets the surface when the entry is invalidated', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $fixture = createTestClassFixture('SurfaceSubject', 'public function test(): string { return "x"; }');
        app(Analyzer::class)->analyze($fixture);

        AnalyzedCache::invalidate($fixture);

        expect(glob($dir.'/*.record'))->toBeEmpty();
        expect(AnalyzedCache::surfaceHash($fixture))->toBeNull();

        unlink($fixture);
        cleanupCacheDir($dir);
    });

    it('has no surface for a path it never analyzed', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        expect(AnalyzedCache::surfaceHash('/nowhere/Missing.php'))->toBeNull();

        cleanupCacheDir($dir);
    });
});

describe('validity by surface', function () {
    /**
     * Two files, one reaching the other, both persisted.
     *
     * @return array{0: string, 1: string}
     */
    function dependentPair(): array
    {
        $dependent = createTestClassFixture('Dependent', 'public function value() {}');
        $dependency = createTestClassFixture('Dependency', 'public function number() {}');

        AnalyzedCache::beginAnalysis($dependency);
        AnalyzedCache::add($dependency, tap(new Scope, fn ($scope) => $scope->setPath($dependency)));
        AnalyzedCache::endAnalysis($dependency);

        AnalyzedCache::beginAnalysis($dependent);
        AnalyzedCache::addDependency($dependency);
        AnalyzedCache::add($dependent, tap(new Scope, fn ($scope) => $scope->setPath($dependent)));
        AnalyzedCache::endAnalysis($dependent);

        return [$dependent, $dependency];
    }

    it('keeps a dependent when the dependency changed but its surface did not', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        [$dependent, $dependency] = dependentPair();
        $unmoved = AnalyzedCache::surfaceHash($dependency);

        sleep(1);
        file_put_contents($dependency, "<?php\nclass Dependency { public function number() { return 2; } }");

        AnalyzedCache::clearMemory();

        // Analyzing the changed file lands on the same surface it had before.
        AnalyzedCache::resolveSurfaceUsing(fn () => $unmoved);

        expect(AnalyzedCache::get($dependent))->not->toBeNull();

        unlink($dependent);
        unlink($dependency);
        cleanupCacheDir($dir);
    });

    it('drops a dependent when the dependency surface moves', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        [$dependent, $dependency] = dependentPair();

        sleep(1);
        file_put_contents($dependency, "<?php\nclass Dependency { public function other(): string { return 'x'; } }");

        AnalyzedCache::clearMemory();

        AnalyzedCache::resolveSurfaceUsing(fn () => 'a-different-surface');

        expect(AnalyzedCache::get($dependent))->toBeNull();

        unlink($dependent);
        unlink($dependency);
        cleanupCacheDir($dir);
    });

    it('drops a dependent two levels up when a surface moves', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $a = createTestClassFixture('AClass', 'public function a() {}');
        $b = createTestClassFixture('BClass', 'public function b() {}');
        $c = createTestClassFixture('CClass', 'public function c() {}');

        // A reaches B, B reaches C. Nothing links A to C.
        AnalyzedCache::beginAnalysis($a);
        AnalyzedCache::addDependency($b);

        AnalyzedCache::beginAnalysis($b);
        AnalyzedCache::addDependency($c);
        AnalyzedCache::add($b, tap(new Scope, fn ($scope) => $scope->setPath($b)));
        AnalyzedCache::endAnalysis($b);

        AnalyzedCache::add($a, tap(new Scope, fn ($scope) => $scope->setPath($a)));
        AnalyzedCache::endAnalysis($a);

        sleep(1);
        file_put_contents($c, "<?php\nclass CClass { public function moved(): string { return 'x'; } }");

        AnalyzedCache::clearMemory();

        AnalyzedCache::resolveSurfaceUsing(fn () => 'a-different-surface');

        expect(AnalyzedCache::get($a))->toBeNull();

        unlink($a);
        unlink($b);
        unlink($c);
        cleanupCacheDir($dir);
    });

    it('keeps a dependent two levels up when only a body changes', function () {
        $dir = createCacheDir();
        AnalyzedCache::enableDiskCache($dir);

        $a = createTestClassFixture('AClass', 'public function a() {}');
        $b = createTestClassFixture('BClass', 'public function b() {}');
        $c = createTestClassFixture('CClass', 'public function c() {}');

        AnalyzedCache::beginAnalysis($a);
        AnalyzedCache::addDependency($b);

        AnalyzedCache::beginAnalysis($b);
        AnalyzedCache::addDependency($c);
        AnalyzedCache::add($b, tap(new Scope, fn ($scope) => $scope->setPath($b)));
        AnalyzedCache::endAnalysis($b);

        AnalyzedCache::add($a, tap(new Scope, fn ($scope) => $scope->setPath($a)));
        AnalyzedCache::endAnalysis($a);

        $unmovedB = AnalyzedCache::surfaceHash($b);
        $unmovedC = AnalyzedCache::surfaceHash($c);

        sleep(1);
        file_put_contents($c, "<?php\nclass CClass { public function c() { return 2; } }");

        AnalyzedCache::clearMemory();

        AnalyzedCache::resolveSurfaceUsing(fn (string $path) => $path === $b ? $unmovedB : $unmovedC);

        expect(AnalyzedCache::get($a))->not->toBeNull();

        unlink($a);
        unlink($b);
        unlink($c);
        cleanupCacheDir($dir);
    });
});

describe('settling cycles', function () {
    it('says which members gave up on a file themselves', function () {
        $root = createTestClassFixture('RootClass', 'public function root() {}');
        $middle = createTestClassFixture('MiddleClass', 'public function middle() {}');
        $inner = createTestClassFixture('InnerClass', 'public function inner() {}');

        // Root reaches middle, middle reaches inner, and inner asks for root
        // while root is still open.
        AnalyzedCache::beginAnalysis($root);
        AnalyzedCache::addDependency($middle);

        AnalyzedCache::beginAnalysis($middle);
        AnalyzedCache::addDependency($inner);

        AnalyzedCache::beginAnalysis($inner);
        AnalyzedCache::addDependency($root);
        AnalyzedCache::noteCycle($root);
        AnalyzedCache::add($inner, tap(new Scope, fn ($scope) => $scope->setPath($inner)));
        AnalyzedCache::endAnalysis($inner);

        AnalyzedCache::add($middle, tap(new Scope, fn ($scope) => $scope->setPath($middle)));
        AnalyzedCache::endAnalysis($middle);

        AnalyzedCache::add($root, tap(new Scope, fn ($scope) => $scope->setPath($root)));
        AnalyzedCache::endAnalysis($root);

        $settled = collect(AnalyzedCache::takeSettled())->pluck('bailed', 'path');

        expect($settled[$inner])->toBeTrue();
        expect($settled[$middle])->toBeFalse();
        expect($settled[$root])->toBeFalse();

        unlink($root);
        unlink($middle);
        unlink($inner);
    });

    it('reports what a member reached, so settling can follow the edges', function () {
        AnalyzedCache::beginAnalysis('/path/to/middle.php');
        AnalyzedCache::addDependency('/path/to/inner.php');
        AnalyzedCache::endAnalysis('/path/to/middle.php');

        expect(AnalyzedCache::dependenciesOf('/path/to/middle.php'))->toBe(['/path/to/inner.php']);
        expect(AnalyzedCache::dependenciesOf('/path/to/unknown.php'))->toBe([]);
    });
});
