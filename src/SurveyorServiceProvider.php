<?php

namespace Laravel\Surveyor;

// TODO: Temp fix, gotta figure this out...
// ini_set('memory_limit', '1G');

use Illuminate\Support\ServiceProvider;
use Laravel\Surveyor\Console\Analyze;
use Laravel\Surveyor\Console\RemoveAbstractClasses;
use Laravel\Surveyor\Console\ScaffoldDocBlockResolversCommand;
use Laravel\Surveyor\Console\ScaffoldResolversCommand;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Parser\Parser;
use Laravel\Surveyor\Resolvers\DocBlockResolver;
use Laravel\Surveyor\Resolvers\NodeResolver;

class SurveyorServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Parser::class);
        $this->app->singleton(DocBlockParser::class);
        $this->app->singleton(NodeResolver::class);
        $this->app->singleton(DocBlockResolver::class);
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
                __DIR__.'/../config/static-analyzer.php' => config_path('static-analyzer.php'),
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
                RemoveAbstractClasses::class,
            ]);
        }
    }
}
