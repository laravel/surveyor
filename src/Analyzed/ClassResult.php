<?php

namespace Laravel\Surveyor\Analyzed;

class ClassResult
{
    /** @var array<string, PropertyResult> */
    protected array $properties = [];

    /** @var array<string, ConstantResult> */
    protected array $constants = [];

    /** @var list<string> */
    protected array $traits = [];

    /** @var array<string, MethodResult> */
    protected array $methods = [];

    /**
     * @param  list<string>  $extends
     * @param  list<string>  $implements
     * @param  array<string, string>  $uses
     */
    public function __construct(
        protected string $name,
        protected string $namespace,
        protected array $extends,
        protected array $implements,
        protected array $uses,
        protected string $filePath,
    ) {
        //
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function addMethod(MethodResult $method): void
    {
        $this->methods[$method->name()] = $method;
    }

    public function extends(...$extends): array|bool
    {
        if (empty($extends)) {
            return $this->extends;
        }

        return in_array($extends, $this->extends);
    }

    public function implements(...$implements): array|bool
    {
        if (empty($implements)) {
            return $this->implements;
        }

        return in_array($implements, $this->implements);
    }

    public function hasMethod(string $name): bool
    {
        return isset($this->methods[$name]);
    }

    public function getMethod(string $name): MethodResult
    {
        return $this->methods[$name];
    }

    public function hasProperty(string $name): bool
    {
        return isset($this->properties[$name]);
    }

    public function getProperty(string $name): PropertyResult
    {
        return $this->properties[$name];
    }

    public function publicProperties(): array
    {
        return array_values(
            array_filter(
                $this->properties,
                fn (PropertyResult $property) => $property->visibility === 'public',
            ),
        );
    }

    public function addProperty(string $name, PropertyResult $property): void
    {
        $this->properties[$name] = $property;
    }

    public function hasConstant(string $name): bool
    {
        return isset($this->constants[$name]);
    }

    public function getConstant(string $name): ConstantResult
    {
        return $this->constants[$name];
    }

    public function hasUse(string $name): bool
    {
        return isset($this->uses[$name]);
    }

    public function getUse(string $name): ?string
    {
        return $this->uses[$name] ?? null;
    }
}
