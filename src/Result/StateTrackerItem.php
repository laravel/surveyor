<?php

namespace Laravel\StaticAnalyzer\Result;

use Laravel\StaticAnalyzer\Types\ArrayType;
use Laravel\StaticAnalyzer\Types\Contracts\Type as TypeContract;
use Laravel\StaticAnalyzer\Types\Type;
use Laravel\StaticAnalyzer\Types\UnionType;

class StateTrackerItem
{
    protected array $variables = [];

    protected array $snapshots = [];

    protected array $activeSnapshots = [];

    public function add(string $name, TypeContract $type, int $lineNumber): void
    {
        $changed = [
            'type' => $type,
            'lineNumber' => $lineNumber,
        ];

        $this->variables[$name] ??= [];
        $this->variables[$name][] = $changed;

        if (count($this->activeSnapshots) > 0) {
            $activeSnapshot = $this->activeSnapshots[count($this->activeSnapshots) - 1];
            $this->snapshots[$activeSnapshot][$name] ??= [];
            $this->snapshots[$activeSnapshot][$name][] = $changed;
        }
    }

    public function narrow(string $name, TypeContract $type, int $lineNumber): void
    {
        $currentType = $this->getAtLine($name, $lineNumber)['type'];

        if (Type::is($currentType, get_class($type))) {
            return;
        }

        if ($currentType instanceof UnionType) {
            $newType = array_filter(
                $currentType->types,
                fn ($t) => Type::is($t, get_class($type)),
            )[0] ?? Type::from($type);
        } else {
            $newType = Type::from($type);
        }

        $this->add($name, $newType, $lineNumber);
    }

    public function unset(string $name, int $lineNumber): void
    {
        $this->add($name, Type::null(), $lineNumber);
    }

    public function unsetArrayKey(string $name, string $key, int $lineNumber): void
    {
        $this->updateArrayKey($name, $key, Type::null(), $lineNumber);
    }

    public function removeType(string $name, int $lineNumber, TypeContract $type): void
    {
        $currentType = $this->getAtLine($name, $lineNumber)['type'];

        if ($currentType instanceof UnionType) {
            $newType = new UnionType(array_filter($currentType->types, fn ($t) => ! Type::isSame($t, $type)));
        } elseif (Type::isSame($currentType, $type)) {
            // TODO: Hm.
            dd('removing type that is the same as the current type??', $currentType, $type);
            $newType = Type::mixed();
        } else {
            $newType = $currentType;
            // dd('current type is not a union type and not the same as the type to remove??', $currentType, $type);
        }

        $this->add($name, $newType, $lineNumber);
    }

    public function removeArrayKeyType(string $name, string $key, TypeContract $type, int $lineNumber): void
    {
        // TODO: Implement
    }

    public function updateArrayKey(string $name, string $key, TypeContract $type, int $lineNumber): void
    {
        $this->variables[$name] ??= [];

        $lastValue = $this->variables[$name][count($this->variables[$name]) - 1] ?? null;

        if ($lastValue === null) {
            $newType = new ArrayType([$key => $type]);
        } elseif ($lastValue['type'] instanceof ArrayType) {
            $newType = new ArrayType(array_merge($lastValue['type']->value, [$key => $type]));
        } elseif ($lastValue['type'] instanceof UnionType) {
            $existingTypes = $lastValue['type']->types;
            $newType = new UnionType(
                array_map(fn ($t) => new ArrayType(array_merge($t->value, [$key => $type])), $existingTypes)
            );
        } else {
            dd('last value is not an array or union type??', $lastValue);
        }

        $changed = [
            'type' => $newType,
            'lineNumber' => $lineNumber,
        ];

        $this->variables[$name][] = $changed;

        if (count($this->activeSnapshots) > 0) {
            $activeSnapshot = $this->activeSnapshots[count($this->activeSnapshots) - 1];
            $this->snapshots[$activeSnapshot][$name] ??= [];
            $this->snapshots[$activeSnapshot][$name][] = $changed;
        }
    }

    public function getAtLine(string $name, int $lineNumber): array
    {
        if (! array_key_exists($name, $this->variables)) {
            return [];
        }

        $lines = array_filter($this->variables[$name], fn ($variable) => $variable['lineNumber'] <= $lineNumber - 1);

        // TODO: Not sure if this is always right...
        if (empty($lines)) {
            // This is the first instance of the variable, just return the actual line number
            return $this->getAtLine($name, $lineNumber + 1);
        }

        return end($lines);
    }

    public function startSnapshot(int $startLine): void
    {
        $this->snapshots[$startLine] = [];
        $this->activeSnapshots[] = $startLine;
    }

    public function endSnapshot(int $startLine): array
    {
        $changed = $this->snapshots[$startLine] ?? [];

        array_pop($this->activeSnapshots);
        unset($this->snapshots[$startLine]);

        return $changed;
    }
}
