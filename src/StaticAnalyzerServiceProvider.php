<?php

namespace Laravel\StaticAnalyzer;

// TODO: Temp fix, gotta figure this out...
// ini_set('memory_limit', '1G');

use Illuminate\Support\ServiceProvider;
use Laravel\StaticAnalyzer\Console\Analyze;
use Laravel\StaticAnalyzer\Console\ScaffoldDocBlockResolversCommand;
use Laravel\StaticAnalyzer\Console\ScaffoldResolversCommand;
use Laravel\StaticAnalyzer\Parser\DocBlockParser;
use Laravel\StaticAnalyzer\Parser\Parser;
use Laravel\StaticAnalyzer\Resolvers\DocBlockResolver;
use Laravel\StaticAnalyzer\Resolvers\NodeResolver;

class StaticAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Parser::class);
        $this->app->singleton(NodeResolver::class);
        $this->app->singleton(DocBlockResolver::class);
        $this->app->singleton(DocBlockParser::class);
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/static-analyzer.php' => config_path('static-analyzer.php'),
            ], 'static-analyzer-config');
        }
    }

    /**
     * Register the package's commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Analyze::class,
                ScaffoldResolversCommand::class,
                ScaffoldDocBlockResolversCommand::class,
            ]);
        }
    }
}
