<?php

namespace Laravel\StaticAnalyzer\Types;

use Illuminate\Support\Facades\Facade;
use ReflectionClass;

class ClassType extends AbstractType implements Contracts\Type
{
    public readonly string $value;

    protected array $genericTypes = [];

    public function __construct(string $value)
    {
        $this->value = ltrim($value, '\\');
    }

    public function setGenericTypes(array $genericTypes): self
    {
        $this->genericTypes = $genericTypes;

        return $this;
    }

    public function isInterface(): bool
    {
        return (new ReflectionClass($this->value))->isInterface();
    }

    public function resolved(): string
    {
        $reflection = new ReflectionClass($this->value);

        if ($reflection->isSubclassOf(Facade::class)) {
            return ltrim(get_class($this->value::getFacadeRoot()), '\\');
        }

        // if (app()->getBindings()[$this->value] ?? null) {
        //     return app()->getBindings()[$this->value]->getConcrete();
        // }

        return $this->value;
    }

    public function id(): string
    {
        return $this->resolved();
    }
}
