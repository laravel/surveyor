<?php

namespace Laravel\StaticAnalyzer\Console;

use Illuminate\Console\Command;
use Laravel\StaticAnalyzer\Analyzer\Analyzer;

class Analyze extends Command
{
    protected $signature = 'analyze {--path=}';

    protected $description = '';

    public function handle(Analyzer $analyzer)
    {
        $path = $this->option('path');

        $path = dirname(__DIR__, 2) . '/workbench/app/Http/Controllers/UserController.php';

        $analyzer->analyze($path);
    }
}
