<?php

namespace Laravel\Surveyor;

use Illuminate\Support\ServiceProvider;
use Laravel\Surveyor\Analysis\Resolver;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ResourceAnalyzer;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Resolvers\DocBlockResolver;
use Laravel\Surveyor\Resolvers\NodeResolver;
use PhpParser\Parser as PhpParserParser;
use PhpParser\ParserFactory;

class SurveyorServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(DocBlockParser::class);
        $this->app->singleton(NodeResolver::class);
        $this->app->singleton(DocBlockResolver::class);
        $this->app->singleton(Analyzer::class);
        $this->app->singleton(ResourceAnalyzer::class);
        $this->app->singleton(Resolver::class);
        $this->app->singleton(PhpParserParser::class, function () {
            return (new ParserFactory)->createForHostVersion();
        });
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureCaching();
    }

    protected function configureCaching()
    {
        if (env('SURVEYOR_CACHE_ENABLED', false)) {
            $cacheDir = env('SURVEYOR_CACHE_DIR', storage_path('surveyor-cache'));
            AnalyzedCache::setCacheDirectory($cacheDir);

            if ($key = env('SURVEYOR_CACHE_KEY', $this->app['config']['app.key'] ?? '')) {
                AnalyzedCache::setKey($key);
            }

            AnalyzedCache::enable();
        }
    }
}
