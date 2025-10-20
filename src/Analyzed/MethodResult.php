<?php

namespace Laravel\Surveyor\Analyzed;

use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;

class MethodResult
{
    public function __construct(
        public readonly string $name,
        public readonly array $parameters,
        public readonly array $returnTypes,
    ) {
        //
    }

    public static function fromScope(Scope $scope): self
    {
        return new static(
            name: $scope->methodName(),
            parameters: $scope->parameters(),
            returnTypes: self::mapReturnTypes($scope->returnTypes()),
        );
    }

    public function returnType(): TypeContract
    {
        return Type::union(...$this->returnTypes);
    }

    /**
     * @param  array<array{type: Type, lineNumber: int}>  $returnTypes
     */
    protected static function mapReturnTypes(array $returnTypes): array
    {
        $results = [];

        foreach ($returnTypes as $returnType) {
            $results[] = $returnType['type'];
        }

        return $results;
    }
}
