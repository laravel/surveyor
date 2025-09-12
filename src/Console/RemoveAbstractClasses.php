<?php

namespace Laravel\Surveyor\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class RemoveAbstractClasses extends Command
{
    protected $signature = 'remove:abstract';

    protected $description = 'Remove abstract classes from resolvers';

    public function handle()
    {
        $base = __DIR__.'/../NodeResolvers';

        collect(File::allFiles($base))->each(function ($file) use ($base) {
            $path = str($file)->after($base)->replace('/', '\\')->replace('.php', '')->prepend('PhpParser\\Node')->toString();

            if (! class_exists($path)) {
                return;
            }

            $reflection = new ReflectionClass($path);

            if ($reflection->isAbstract()) {
                unlink($file->getPathname());
            }
        });
    }
}
