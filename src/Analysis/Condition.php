<?php

namespace Laravel\StaticAnalyzer\Analysis;

use Laravel\StaticAnalyzer\Types\Contracts\Type as TypeContract;
use Laravel\StaticAnalyzer\Types\Type;
use Laravel\StaticAnalyzer\Types\UnionType;

class Condition
{
    protected $falseCallback = null;

    protected $trueCallback = null;

    protected ?TypeContract $typeToSet = null;

    protected ?TypeContract $typeToRemove = null;

    public function __construct(
        public readonly string $variable,
        public TypeContract $type,
        public readonly int $line,
    ) {
        //
    }

    public function whenFalse(callable $callback): self
    {
        $this->falseCallback = $callback;

        return $this;
    }

    public function whenTrue(callable $callback): self
    {
        $this->trueCallback = $callback;

        return $this;
    }

    public function makeTrue(): self
    {
        if ($this->trueCallback) {
            ($this->trueCallback)($this->type);
        }

        return $this;
    }

    public function makeFalse(): self
    {
        if ($this->falseCallback) {
            ($this->falseCallback)($this->type);
        }

        return $this;
    }

    public function setType(TypeContract $type): self
    {
        $this->typeToSet = $type;
        $this->typeToRemove = null;

        return $this;
    }

    public function removeType(TypeContract $type): self
    {
        $this->typeToRemove = $type;
        $this->typeToSet = null;

        return $this;
    }

    public function apply(): TypeContract
    {
        $this->applyTrue();
        $this->applyFalse();

        return $this->type;
    }

    protected function applyTrue(): void
    {
        if (! $this->typeToSet) {
            return;
        }

        if ($this->type instanceof UnionType) {
            $newType = array_filter(
                $this->type->types,
                fn ($t) => Type::is($t, $this->typeToSet),
            )[0] ?? $this->typeToSet;
        } else {
            $newType = Type::is($this->type, $this->typeToSet) ? $this->type : $this->typeToSet;
        }

        $this->type = $newType;
    }

    protected function applyFalse(): void
    {
        if (! $this->typeToRemove || Type::is($this->type, $this->typeToRemove)) {
            return;
        }

        if ($this->type instanceof UnionType) {
            $newType = Type::union(...array_filter(
                $this->type->types,
                fn ($t) => ! Type::is($t, $this->typeToRemove),
            ));
        } else {
            $newType = Type::is($this->type, $this->typeToRemove) ? Type::mixed() : $this->typeToRemove;
        }

        $this->type = $newType;
    }
}
