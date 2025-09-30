<?php

namespace Laravel\Surveyor\Types;

use Illuminate\Support\Facades\Facade;
use ReflectionClass;

class ClassType extends AbstractType implements Contracts\Type
{
    public readonly string $value;

    protected array $genericTypes = [];

    protected array $constructorArguments = [];

    public function __construct(string $value)
    {
        $this->value = ltrim($value, '\\');
    }

    public function setConstructorArguments(array $constructorArguments): self
    {
        $this->constructorArguments = $constructorArguments;

        return $this;
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
        if (! class_exists($this->value) && ! interface_exists($this->value)) {
            // TODO: This *shouldn't* happen, but it does. Need to figure out why.
            return $this->value;
        }

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
