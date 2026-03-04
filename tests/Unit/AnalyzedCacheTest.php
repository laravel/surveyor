<?php

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->cacheDir = __DIR__ . '/../../cache_test';
    if (!is_dir($this->cacheDir)) {
        mkdir($this->cacheDir, 0755, true);
    }
    AnalyzedCache::setCacheDirectory($this->cacheDir);
    AnalyzedCache::setKey('base-secret-key');
    AnalyzedCache::enable();
});

afterEach(function () {
    if (is_dir($this->cacheDir)) {
        File::deleteDirectory($this->cacheDir);
    }
    AnalyzedCache::clearMemory();
    AnalyzedCache::disable();
});

test('it signs and verifies cache data', function () {
    $path = __DIR__ . '/AnalyzedCacheTest.php';
    $scope = new Scope();
    $scope->setPath($path);
    
    AnalyzedCache::add($path, $scope);
    
    // Clear memory to force disk read
    AnalyzedCache::clearMemory();
    
    $cached = AnalyzedCache::get($path);
    expect($cached)->not->toBeNull()
        ->and($cached->path())->toBe($path);
});

test('it rejects cache with invalid signature', function () {
    $path = __DIR__ . '/AnalyzedCacheTest.php';
    $scope = new Scope();
    $scope->setPath($path);
    
    AnalyzedCache::add($path, $scope);
    
    // Manually tamper with the cache file
    $cacheFiles = glob($this->cacheDir . '/*.cache');
    $cacheFile = $cacheFiles[0];
    $content = file_get_contents($cacheFile);
    
    // Change a character in the serialized data part (after the first ':')
    $parts = explode(':', $content, 2);
    $tampered = $parts[0] . ':' . $parts[1] . 'tampered';
    file_put_contents($cacheFile, $tampered);
    
    // Clear memory to force disk read
    AnalyzedCache::clearMemory();
    
    $cached = AnalyzedCache::get($path);
    expect($cached)->toBeNull();
    
    // Verify file was invalidated (deleted)
    expect(file_exists($cacheFile))->toBeFalse();
});

test('it rejects cache if key changes', function () {
    $path = __DIR__ . '/AnalyzedCacheTest.php';
    $scope = new Scope();
    $scope->setPath($path);
    
    AnalyzedCache::add($path, $scope);
    
    // Clear memory and change the key
    AnalyzedCache::clearMemory();
    AnalyzedCache::setKey('different-secret-key');
    
    $cached = AnalyzedCache::get($path);
    expect($cached)->toBeNull();
});
