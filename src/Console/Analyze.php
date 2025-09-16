<?php

namespace Laravel\Surveyor\Console;

use Illuminate\Console\Command;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Debug\Debug;

class Analyze extends Command
{
    protected $signature = 'analyze {--path=} {--dump} {--v} {--vv} {--vvv}';

    protected $description = '';

    public function handle(Analyzer $analyzer)
    {
        Debug::$dump = (bool) $this->option('dump');
        Debug::$logLevel = match (true) {
            $this->option('v') => 1,
            $this->option('vv') => 2,
            $this->option('vvv') => 3,
            default => 0,
        };

        $path = $this->option('path');

        $result = $analyzer->analyze(getcwd().'/'.$path)->analyzed();

        foreach ($result as $class) {
            foreach ($class->children() as $method) {
                dump($method->returnTypes());
            }
        }

        dd($result);
    }
}
