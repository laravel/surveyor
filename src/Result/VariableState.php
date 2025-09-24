<?php

namespace Laravel\Surveyor\Result;

use Laravel\Surveyor\Types\Contracts\Type;
use PhpParser\NodeAbstract;

class VariableState
{
    protected bool $nonTerminable = false;

    protected ?string $references = null;

    protected bool $byRef = false;

    public function __construct(
        protected Type $type,
        protected int $startLine,
        protected int $startTokenPos,
        protected int $endLine,
        protected int $endTokenPos,
        protected ?int $terminatedAt = null,
    ) {
        //
    }

    public static function isSame(self $a, self $b): bool
    {
        return $a->startLine() === $b->startLine()
            && $a->startTokenPos() === $b->startTokenPos()
            && $a->endLine() === $b->endLine()
            && $a->endTokenPos() === $b->endTokenPos();
    }

    public static function fromNode(Type $type, NodeAbstract $node): self
    {
        return new self(
            $type,
            $node->getStartLine(),
            $node->getStartTokenPos(),
            $node->getEndLine(),
            $node->getEndTokenPos(),
        );
    }

    public function byRef(bool $byRef = true): self
    {
        $this->byRef = $byRef;

        return $this;
    }

    public function addReference(string $referenceVar): void
    {
        $this->references = $referenceVar;
    }

    public function references(string $toCheck): bool
    {
        return $this->references === $toCheck;
    }

    public function markNonTerminable(): self
    {
        $this->terminatedAt = null;
        $this->nonTerminable = true;

        return $this;
    }

    public function terminate(int $line): self
    {
        if ($this->nonTerminable) {
            return $this;
        }

        $this->terminatedAt = $line;

        return $this;
    }

    public function type(): Type
    {
        return $this->type;
    }

    public function startLine(): int
    {
        return $this->startLine;
    }

    public function endLine(): int
    {
        return $this->endLine;
    }

    public function startTokenPos(): int
    {
        return $this->startTokenPos;
    }

    public function endTokenPos(): int
    {
        return $this->endTokenPos;
    }

    public function terminatedAt(): ?int
    {
        return $this->terminatedAt;
    }

    // TODO: Is this the right name?
    public function isTerminatedAfter(int $line): bool
    {
        if ($this->terminatedAt === null) {
            return true;
        }

        return $this->terminatedAt >= $line;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'startLine' => $this->startLine,
            'startTokenPos' => $this->startTokenPos,
            'endLine' => $this->endLine,
            'endTokenPos' => $this->endTokenPos,
            'terminatedAt' => $this->terminatedAt,
        ];
    }
}
