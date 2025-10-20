<?php

namespace Laravel\Surveyor\Analyzed;

use Laravel\Surveyor\Analysis\EntityType;
use Laravel\Surveyor\Analysis\Scope;

class ClassResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $namespace,
        public readonly array $extends,
        public readonly array $implements,
        /** @var array<string, PropertyResult> */
        public readonly array $properties,
        public readonly array $constants,
        public readonly array $traits,
        public readonly array $uses,
        public readonly array $methods,
    ) {
        //
    }

    public static function fromScope(Scope $scope): self
    {
        return new static(
            name: $scope->entityName(),
            namespace: $scope->namespace(),
            extends: $scope->extends(),
            implements: $scope->implements(),
            properties: self::mapProperties($scope->state()->properties()->variables()),
            constants: self::mapConstants($scope->constants()),
            traits: $scope->traits(),
            uses: $scope->uses(),
            methods: self::mapMethods($scope->children()),
        );
    }

    /**
     * @param  array<Scope>  $children
     */
    protected static function mapMethods(array $children): array
    {
        $results = [];

        foreach ($children as $child) {
            if ($child->entityType() === EntityType::METHOD_TYPE) {
                $results[$child->methodName()] = MethodResult::fromScope($child);
            }
        }

        return $results;
    }

    protected static function mapConstants(array $constants): array
    {
        $results = [];

        foreach ($constants as $name => $constant) {
            $results[$name] = new ConstantResult(
                name: $name,
                type: $constant,
            );
        }

        return $results;
    }

    protected static function mapProperties(array $properties): array
    {
        $results = [];

        foreach ($properties as $name => $propertyStates) {
            $results[$name] = new PropertyResult(
                name: $name,
                type: $propertyStates[0]->type(),
            );
        }

        return $results;
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
