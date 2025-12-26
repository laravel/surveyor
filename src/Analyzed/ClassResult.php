<?php

namespace Laravel\Surveyor\Analyzed;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Surveyor\Types\Type;

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

    protected bool $arrayable = false;

    /**
     * @param  list<string>  $extends
     * @param  list<string>  $implements
     * @param  array<string, string>  $uses
     */
    public function __construct(
        protected string $name,
        protected ?string $namespace,
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

    public function isJsonSerializable(): bool
    {
        return $this->implements(JsonSerializable::class);
    }

    public function isArrayable(): bool
    {
        return $this->implements(Arrayable::class);
    }

    public function asJson(): ?MethodResult
    {
        if (! $this->isJsonSerializable()) {
            return null;
        }

        return $this->getMethod('jsonSerialize');
    }

    public function asArray(): ?MethodResult
    {
        if (! $this->isArrayable()) {
            return null;
        }

        return $this->getMethod('toArray');
    }

    public function addMethod(MethodResult $method): void
    {
        if (isset($this->methods[$method->name()])) {
            $existing = $this->methods[$method->name()];

            if ($existing->isModelRelation()) {
                $method->flagAsModelRelation();
            }

            $existingTypes = array_column($existing->returnTypes(), 'type');
            $newTypes = array_column($method->returnTypes(), 'type');
            $mergedType = Type::union(...$existingTypes, ...$newTypes);

            $method->addReturnType($mergedType, 0);
        }

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

        return count(array_intersect($implements, $this->implements)) > 0;
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

    /**
     * @return list<MethodResult>
     */
    public function publicMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return list<PropertyResult>
     */
    public function publicProperties(): array
    {
        return array_values(
            array_filter(
                $this->properties,
                fn (PropertyResult $property) => $property->visibility === 'public',
            ),
        );
    }

    public function addProperty(PropertyResult $property): void
    {
        $this->properties[$property->name] = $property;
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
