<?php

namespace Laravel\StaticAnalyzer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\StructureDiscoverer\Discover;

class ScaffoldResolversCommand extends Command
{
    protected $signature = 'scaffold:resolvers';

    protected $description = 'AI was going too slowly';

    public function handle()
    {
        collect(Discover::in(__DIR__ . '/../../vendor/nikic/php-parser')->classes()->get())->filter(fn(string $c) => str_contains($c, '\\Node\\'))->each(function ($class) {
            $resolverClassFqn = str($class)->after('Node\\');
            $resolverClassNamespace = str($class)->after('Node\\')->beforeLast('\\');
            $resolverClass = $resolverClassFqn->afterLast('\\');
            $path = __DIR__ . '/../Resolvers/' . $resolverClassFqn->replace('\\', '/')->append('.php')->toString();

            if (file_exists($path)) {
                $this->warn("File already exists: {$path}");
                return;
            }

            $contents = <<<PHP
<?php

namespace Laravel\StaticAnalyzer\Resolvers\\$resolverClassNamespace;

use Laravel\StaticAnalyzer\Resolvers\AbstractResolver;
use PhpParser\Node;

class {$resolverClass} extends AbstractResolver
{
    public function resolve(Node\\{$resolverClassFqn} \$node)
    {
        dd(\$node, \$node::class . ' not implemented yet');
    }
}
PHP;

            File::ensureDirectoryExists(dirname($path));
            File::put($path, $contents);

            $this->info("Created file: {$path}");
        });
    }
}
