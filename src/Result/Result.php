<?php

namespace Laravel\StaticAnalyzer\Result;

class Result
{
    protected array $statements = [];

    public function __construct(
        protected string $path,
    ) {
        //
    }
}
