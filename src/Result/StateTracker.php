<?php

namespace Laravel\StaticAnalyzer\Result;

use Laravel\StaticAnalyzer\Types\ClassType;
use Laravel\StaticAnalyzer\Types\Contracts\Type;

class StateTracker
{
    protected StateTrackerItem $variableTracker;

    protected StateTrackerItem $propertyTracker;

    public function __construct()
    {
        $this->variableTracker = new StateTrackerItem;
        $this->propertyTracker = new StateTrackerItem;
    }

    public function addVariable(string $name, Type $type, int $lineNumber): void
    {
        $this->variableTracker->add($name, $type, $lineNumber);
    }

    public function addProperty(string $name, Type $type, int $lineNumber): void
    {
        $this->propertyTracker->add($name, $type, $lineNumber);
    }

    public function updateVariableArrayKey(string $name, string $key, Type $type, int $lineNumber): void
    {
        $this->variableTracker->updateArrayKey($name, $key, $type, $lineNumber);
    }

    public function updatePropertyArrayKey(string $name, string $key, Type $type, int $lineNumber): void
    {
        $this->propertyTracker->updateArrayKey($name, $key, $type, $lineNumber);
    }

    public function getVariableAtLine(string $name, int $lineNumber): array
    {
        return $this->variableTracker->getAtLine($name, $lineNumber);
    }

    public function getPropertyAtLine(string $name, int $lineNumber): array
    {
        return $this->propertyTracker->getAtLine($name, $lineNumber);
    }

    public function startVariableSnapshot(int $startLine): void
    {
        $this->variableTracker->startSnapshot($startLine);
    }

    public function endVariableSnapshot(int $startLine): array
    {
        return $this->variableTracker->endSnapshot($startLine);
    }

    public function startPropertySnapshot(int $startLine): void
    {
        $this->propertyTracker->startSnapshot($startLine);
    }

    public function endPropertySnapshot(int $startLine): array
    {
        return $this->propertyTracker->endSnapshot($startLine);
    }

    public function setThis(string $className): void
    {
        $this->addVariable('this', new ClassType($className), 0);
    }
}
