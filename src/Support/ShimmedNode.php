<?php

namespace Laravel\Surveyor\Support;

use PhpParser\NodeAbstract;

class ShimmedNode extends NodeAbstract
{
    public function __construct(
        protected int $line,
        protected int $tokenPos,
        protected int $endLine,
        protected int $endTokenPos,
        protected ?int $terminatedAt = null,
    ) {
        //
    }

    public function getStartLine(): int
    {
        return $this->line;
    }

    public function getStartTokenPos(): int
    {
        return $this->tokenPos;
    }

    public function getEndLine(): int
    {
        return $this->endLine;
    }

    public function getEndTokenPos(): int
    {
        return $this->endTokenPos;
    }

    public function getType(): string
    {
        return 'NodeAbstract';
    }

    public function getSubNodeNames(): array
    {
        return [];
    }

    public function terminatedAt(): ?int
    {
        return $this->terminatedAt;
    }
}
