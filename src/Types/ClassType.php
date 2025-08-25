<?php

namespace Laravel\StaticAnalyzer\Types;

use Illuminate\Support\Facades\Facade;
use ReflectionClass;

class ClassType extends AbstractType implements Contracts\Type
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $this->value = ltrim($value, '\\');
    }

    public function resolved(): string
    {
        $reflection = new ReflectionClass($this->value);

        if ($reflection->isSubclassOf(Facade::class)) {
            // dd(
            //     'oh hye',
            //     $this->value::getFacadeRoot(),
            //     get_class($this->value::getFacadeRoot())
            // );

            return ltrim(get_class($this->value::getFacadeRoot()), '\\');
        }

        return $this->value;
    }

    public function id(): string
    {
        return $this->resolved();
    }
}
