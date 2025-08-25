<?php

namespace Laravel\StaticAnalyzer\Types\Contracts;

interface Type
{
    public function isOptional(): bool;

    public function isNullable(): bool;

    public function required(bool $required = true): static;

    public function optional(): static;

    public function nullable(bool $nullable = true): static;

    public function id(): string;
}
