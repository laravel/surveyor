<?php

namespace Laravel\Surveyor\Analysis;

use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PhpParser\NodeAbstract;

class Condition
{
    protected $falseCallback = null;

    protected $trueCallback = null;

    protected bool $currentState = true;

    public function __construct(
        public readonly NodeAbstract $node,
        public TypeContract $type,
    ) {
        //
    }

    public static function from(NodeAbstract $node, TypeContract $type): self
    {
        return new self($node, $type);
    }

    public function whenFalse(callable $callback): self
    {
        $this->falseCallback = $callback;

        return $this;
    }

    /**
     * @param  callable(self, TypeContract): void  $callback
     */
    public function whenTrue(callable $callback): self
    {
        $this->trueCallback = $callback;

        return $this;
    }

    /**
     * @param  callable(self, TypeContract): void  $callback
     */
    public function toggle(): self
    {
        $this->currentState = ! $this->currentState;

        return $this;
    }

    public function makeTrue(): self
    {
        $this->currentState = true;

        return $this;
    }

    public function makeFalse(): self
    {
        $this->currentState = false;

        return $this;
    }

    public function hasConditions(): bool
    {
        return $this->trueCallback !== null || $this->falseCallback !== null;
    }

    public function setType(TypeContract $type): self
    {
        if ($this->type instanceof UnionType) {
            $newType = array_filter(
                $this->type->types,
                fn($t) => Type::is($t, $type),
            )[0] ?? $type;
        } else {
            $newType = Type::is($this->type, $type) ? $this->type : $type;
        }

        $this->type = $newType;

        return $this;
    }

    public function removeType(TypeContract $type): self
    {
        if ($this->type instanceof UnionType) {
            $newType = Type::union(...array_filter(
                $this->type->types,
                fn($t) => ! Type::is($t, $type),
            ));
        } else {
            $newType = Type::is($this->type, $type) ? Type::mixed() : $type;
        }

        $this->type = $newType;

        return $this;
    }

    public function apply(): TypeContract
    {
        if ($this->currentState) {
            $this->applyTrue();
        } else {
            $this->applyFalse();
        }

        return $this->type;
    }

    protected function applyTrue(): void
    {
        if ($this->trueCallback) {
            ($this->trueCallback)($this, $this->type);
        }
    }

    protected function applyFalse(): void
    {
        if ($this->falseCallback) {
            ($this->falseCallback)($this, $this->type);
        }
    }
}
