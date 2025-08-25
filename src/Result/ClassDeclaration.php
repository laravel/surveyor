<?php

namespace Laravel\StaticAnalyzer\Result;

class ClassDeclaration extends AbstractResult
{
    public function __construct(
        public string $name,
        public array $extends,
        public array $implements,
        public array $properties,
        public array $methods,
        public array $constants,
    ) {
        //
    }
}
