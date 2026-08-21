<?php

namespace Laravel\Surveyor\Analyzed;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Concerns\HasIgnoreMarker;
use Laravel\Surveyor\Types\Type;

class ClassLikeResult
{
    use HasIgnoreMarker;

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
        protected EntityType $entityType,
    ) {
        //
    }

    public function entityType(): EntityType
    {
        return $this->entityType;
    }

    public function isInterface(): bool
    {
        return $this->entityType === EntityType::INTERFACE_TYPE;
    }

    public function isClass(): bool
    {
        return $this->entityType === EntityType::CLASS_TYPE;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function namespace(): ?string
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

            if ($existing->ignoreMarker() !== null) {
                $method->flagAsIgnored($existing->ignoreMarker());
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

        return count(array_intersect($extends, $this->extends)) > 0;
    }

    public function implements(...$implements): array|bool
    {
        if (empty($implements)) {
            return $this->implements;
        }

        return count(array_intersect($implements, $this->implements)) > 0;
    }

    /**
     * Every way of reading a member out of a result hides the ones marked to be
     * left out: looking one up by name as much as listing them. A caller that
     * needs to see past that, to read a marker rather than act on one, asks for
     * it by name through method() or property().
     */
    public function hasMethod(string $name): bool
    {
        return $this->getMethod($name) !== null;
    }

    public function getMethod(string $name): ?MethodResult
    {
        $method = $this->method($name);

        return $method === null || $method->isIgnored() ? null : $method;
    }

    public function method(string $name): ?MethodResult
    {
        return $this->methods[$name] ?? null;
    }

    public function hasProperty(string $name): bool
    {
        return $this->getProperty($name) !== null;
    }

    public function getProperty(string $name): ?PropertyResult
    {
        $property = $this->property($name);

        return $property === null || $property->isIgnored() ? null : $property;
    }

    public function property(string $name): ?PropertyResult
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * @return array<string, MethodResult>
     */
    public function publicMethods(): array
    {
        return array_filter(
            $this->methods,
            fn (MethodResult $method) => ! $method->isIgnored(),
        );
    }

    /**
     * @return list<PropertyResult>
     */
    public function publicProperties(): array
    {
        return array_values(
            array_filter(
                $this->properties,
                fn (PropertyResult $property) => $property->visibility === 'public' && ! $property->isIgnored(),
            ),
        );
    }

    public function addProperty(PropertyResult $property): void
    {
        $this->properties[$property->name] = $property;
    }

    /**
     * @return array<string, PropertyResult>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    public function hasConstant(string $name): bool
    {
        return isset($this->constants[$name]);
    }

    public function getConstant(string $name): ConstantResult
    {
        return $this->constants[$name];
    }

    public function addConstant(ConstantResult $constant): void
    {
        $this->constants[$constant->name] = $constant;
    }

    /**
     * @return array<string, ConstantResult>
     */
    public function constants(): array
    {
        return $this->constants;
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
