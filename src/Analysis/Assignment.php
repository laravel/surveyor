<?php

namespace Laravel\StaticAnalyzer\Analysis;

class Assignment
{
    public function __construct(
        public readonly string $target,
        public readonly mixed $value,
        public readonly int $line,
        public readonly array $pathConditions = [],
        public readonly ?string $valueType = null
    ) {
        //
    }

    public function isReachableWith(array $activeConditions): bool
    {
        if (empty($this->pathConditions)) {
            return true; // Unconditional assignment
        }

        foreach ($this->pathConditions as $condition => $expectedValue) {
            if (!isset($activeConditions[$condition])) {
                return false; // Condition not met in this path
            }

            if ($activeConditions[$condition] !== $expectedValue) {
                return false; // Condition value doesn't match
            }
        }

        return true;
    }

    public function getPathConditionsString(): string
    {
        if (empty($this->pathConditions)) {
            return 'always';
        }

        $conditions = [];
        foreach ($this->pathConditions as $condition => $value) {
            $conditions[] = $condition . ' === ' . var_export($value, true);
        }

        return implode(' && ', $conditions);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s = %s at line %d (when %s)',
            $this->target,
            var_export($this->value, true),
            $this->line,
            $this->getPathConditionsString()
        );
    }
}
