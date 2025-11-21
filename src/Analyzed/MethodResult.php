<?php

namespace Laravel\Surveyor\Analyzed;

use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;

class MethodResult
{
    /** @var array<string, TypeContract> */
    protected array $parameters = [];

    /** @var array<array{type: TypeContract, lineNumber: int}> */
    protected array $returnTypes = [];

    /** @var array<string, array<string, array>> */
    protected array $validationRules = [];

    protected bool $modelRelation = false;

    public function __construct(
        protected readonly string $name,
    ) {
        //
    }

    public function name(): string
    {
        return $this->name;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function validationRules(): array
    {
        return $this->validationRules;
    }

    public function returnType(): TypeContract
    {
        return Type::union(...array_column($this->returnTypes, 'type'));
    }

    public function flagAsModelRelation(): void
    {
        $this->modelRelation = true;
    }

    public function isModelRelation(): bool
    {
        return $this->modelRelation;
    }

    public function addValidationRule(string $key, array $rules): void
    {
        $this->validationRules[$key] = $rules;
    }

    public function addReturnType(TypeContract $type, int $lineNumber): void
    {
        $this->returnTypes[] = [
            'type' => $type,
            'lineNumber' => $lineNumber,
        ];
    }

    public function addParameter(string $name, TypeContract $type): void
    {
        $this->parameters[$name] = $type;
    }
}
