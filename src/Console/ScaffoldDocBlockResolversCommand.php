<?php

namespace Laravel\StaticAnalyzer\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\StructureDiscoverer\Discover;

class ScaffoldDocBlockResolversCommand extends Command
{
    protected $signature = 'scaffold:doc-block-resolvers';

    protected $description = 'AI was going too slowly';

    public function handle()
    {
        collect(Discover::in(__DIR__ . '/../../vendor/phpstan/phpdoc-parser')->classes()->get())->filter(fn(string $c) => str_contains($c, '\\Ast\\'))->each(function ($class) {
            $resolverClassFqn = str($class)->after('Ast\\');
            $resolverClassNamespace = str($class)->after('Ast\\')->beforeLast('\\');
            $resolverClass = $resolverClassFqn->afterLast('\\');
            $path = __DIR__ . '/../DocBlockResolvers/' . $resolverClassFqn->replace('\\', '/')->append('.php')->toString();

            if (file_exists($path)) {
                $this->warn("File already exists: {$path}");

                return;
            }

            $contents = <<<PHP
<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\\$resolverClassNamespace;

use Laravel\StaticAnalyzer\Resolvers\AbstractResolver;
use PHPStan\PhpDocParser\Ast;

class {$resolverClass} extends AbstractResolver
{
    public function resolve(Ast\\{$resolverClassFqn} \$node)
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
