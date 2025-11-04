<?php

namespace Laravel\Surveyor\Result;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Support\ShimmedNode;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PhpParser\NodeAbstract;
use Throwable;

class StateTrackerItem
{
    /** @var array<string, VariableState[]> */
    protected array $variables = [];

    /** @var array<string, VariableState[]> */
    protected array $snapshots = [];

    /** @var string[] */
    protected array $activeSnapshots = [];

    public function add(string $name, TypeContract $type, NodeAbstract $node, bool $fromDocBlock = false): VariableState
    {
        $variableState = VariableState::fromNode($type, $node, $fromDocBlock);

        if (property_exists($node, 'byRef')) {
            $variableState->byRef($node->byRef);
        }

        $this->updateSnapshotOrVariable($name, $variableState);

        return $variableState;
    }

    public function getActiveSnapshotKey(): ?string
    {
        return $this->activeSnapshots[count($this->activeSnapshots) - 1] ?? null;
    }

    public function variables(): array
    {
        return $this->variables;
    }

    public function addManually(
        string $name,
        TypeContract $type,
        int $line,
        int $tokenPos,
        int $endLine,
        int $endTokenPos,
        ?int $terminatedAt = null
    ): void {
        $this->add($name, $type, new ShimmedNode($line, $tokenPos, $endLine, $endTokenPos, $terminatedAt));
    }

    protected function getAttributes(TypeContract $type, NodeAbstract $node): VariableState
    {
        $state = VariableState::fromNode($type, $node);

        if ($node instanceof ShimmedNode && $node->terminatedAt() !== null) {
            $state->terminate($node->terminatedAt());
        }

        return $state;
    }

    public function narrow(string $name, TypeContract $type, NodeAbstract $node): void
    {
        $currentType = $this->getAtLine($name, $node)?->type();

        if ($currentType !== null && Type::is($currentType, $type)) {
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

        $this->add($name, $newType, $node);
    }

    public function unset(string $name, NodeAbstract $node): void
    {
        $this->add($name, Type::null(), $node);
    }

    public function unsetArrayKey(string $name, string|array $key, NodeAbstract $node): void
    {
        $this->updateArrayKey($name, $key, Type::null(), $node);
    }

    public function removeType(string $name, NodeAbstract $node, TypeContract $type): void
    {
        $currentType = $this->getAtLine($name, $node)?->type();

        if ($currentType instanceof UnionType) {
            $newType = new UnionType(array_filter($currentType->types, fn ($t) => ! Type::isSame($t, $type)));
        } elseif (Type::isSame($currentType, $type)) {
            // TODO: Hm.
            dd('removing type that is the same as the current type??', $currentType, $type, $currentType->id(), $type->id());
            $newType = Type::mixed();
        } else {
            $newType = $currentType;
            // dd('current type is not a union type and not the same as the type to remove??', $currentType, $type);
        }

        $this->add($name, $newType, $node);
    }

    public function removeArrayKeyType(string $name, string|array $key, TypeContract $type, NodeAbstract $node): void
    {
        // TODO: Implement
    }

    public function updateArrayKey(string $name, string|array $key, TypeContract $type, NodeAbstract $node): void
    {
        $lastValue = $this->getLastSnapshotValue($name) ?? $this->getAtLine($name, $node);
        $newType = $this->resolveArrayKeyType($lastValue, $key, $type);
        $changed = $this->getAttributes($newType, $node);

        $this->updateSnapshotOrVariable($name, $changed);
    }

    protected function updateSnapshotOrVariable(string $name, VariableState $variableState): void
    {
        $activeSnapshot = $this->getActiveSnapshotKey();

        if ($activeSnapshot) {
            Debug::log('🆕 Updating snapshot', [
                'name' => $name,
                'changes' => $variableState->toArray(),
                'snapshot' => $activeSnapshot,
            ], level: 3);

            $this->snapshots[$activeSnapshot][$name] ??= [];

            foreach ($this->snapshots[$activeSnapshot][$name] as $state) {
                if (VariableState::isSame($state, $variableState)) {
                    return;
                }
            }

            foreach ($this->snapshots[$activeSnapshot] as $varName => $states) {
                if ($varName === $name) {
                    continue;
                }

                foreach ($states as $state) {
                    if ($state->references($name)) {
                        $this->updateSnapshotOrVariable($varName, $variableState);
                    }
                }
            }

            $this->snapshots[$activeSnapshot][$name][] = $variableState;
        } else {
            Debug::log('🆕 Updating variable', [
                'name' => $name,
                'changes' => $variableState,
            ], level: 3);

            $this->variables[$name] ??= [];

            foreach ($this->variables[$name] as $state) {
                if (VariableState::isSame($state, $variableState)) {
                    return;
                }
            }

            $this->variables[$name][] = $variableState;
        }
    }

    public function getLastSnapshotValue(string $name): ?VariableState
    {
        $activeSnapshot = $this->getActiveSnapshotKey();

        if (! $activeSnapshot) {
            return null;
        }

        $values = $this->snapshots[$activeSnapshot][$name] ?? [];

        return $values[count($values) - 1] ?? null;
    }

    public function getLastValue(string $name): ?VariableState
    {
        return $this->variables[$name][count($this->variables[$name]) - 1] ?? null;
    }

    public function get(string $name): ?TypeContract
    {
        return $this->getLastValue($name)?->type();
    }

    protected function resolveArrayKeyType(?VariableState $lastValue, string|array $key, TypeContract $type): TypeContract
    {
        $key = is_array($key) ? $key : [$key];
        $newArray = Arr::undot([implode('.', $key) => $type]);

        if ($lastValue === null || $lastValue->type() instanceof MixedType) {
            return new ArrayType($newArray);
        }

        if ($lastValue->type() instanceof ArrayType) {
            return new ArrayType(array_merge($lastValue->type()->value, $newArray));
        }

        if ($lastValue->type() instanceof ArrayShapeType) {
            return $lastValue->type()->keyType;
        }

        if ($lastValue->type() instanceof UnionType) {
            $existingTypes = $lastValue->type()->types;

            try {
                return new UnionType(
                    array_map(fn ($t) => ! $t instanceof ArrayType ? $t : new ArrayType(array_merge($t->value, $newArray)), $existingTypes)
                );
            } catch (Throwable $e) {
                Debug::error('Merging union types: '.$e->getMessage());
                Debug::ddAndOpen($key, $type, $existingTypes, $this, $e->getMessage(), 't->value is null??');
            }
        }

        if ($lastValue->type() instanceof StringType) {
            // Treating string as array
            return Type::string();
        }

        if ($lastValue->type() instanceof IntType) {
            return Type::int();
        }

        if ($lastValue->type() instanceof ClassType && $lastValue->type()->value === 'SplFixedArray') {
            return Type::int();
        }

        return Type::mixed();
    }

    public function getAtLine(string $name, NodeAbstract $node): ?VariableState
    {
        return $this->getAtLineFromSnapshot($name, $node)
            ?? $this->getAtLineFromVariables($name, $node);
        // ?? throw new InvalidArgumentException(
        //     'No result found for `' . $name . '` at line ' . $node->getStartLine() . ' and position ' . $node->getStartTokenPos(),
        // );
    }

    protected function getAtLineFromSnapshot(string $name, NodeAbstract $node): ?VariableState
    {
        foreach (array_reverse($this->snapshots) as $snapshot) {
            if ($result = $this->findAtLine($snapshot[$name] ?? [], $node)) {
                return $result;
            }
        }

        return null;
    }

    protected function getAtLineFromVariables(string $name, NodeAbstract $node): ?VariableState
    {
        return $this->findAtLine($this->variables[$name] ?? [], $node);
    }

    protected function findAtLine(array $variables, NodeAbstract $node): ?VariableState
    {
        $lines = array_filter(
            $variables,
            fn ($variable) => $variable->startLine() <= $node->getStartLine()
                && $variable->startTokenPos() <= $node->getStartTokenPos()
                && $variable->isTerminatedAfter($node->getStartLine()),
        );

        $result = end($lines);

        if ($result === false) {
            return null;
        }

        if ($result->startLine() !== $node->getStartLine()) {
            return $result;
        }

        // Trying to retrieve a value at the same line number as a possible assignment, so return the previous value if it exists
        $newResult = prev($lines);

        if ($newResult) {
            return $newResult;
        }

        // If no previous value exists, return the current value
        return $result;
    }

    protected function getSnapshotKey(NodeAbstract $node): string
    {
        return $node->getStartLine().':'.$node->getStartTokenPos();
    }

    public function startSnapshot(NodeAbstract $node): void
    {
        $key = $this->getSnapshotKey($node);

        Debug::log('📸 Starting snapshot', [
            'key' => $key,
            'node' => get_class($node),
        ], level: 3);

        $this->snapshots[$key] = [];
        $this->activeSnapshots[] = $key;
    }

    public function endSnapshot(NodeAbstract $node): array
    {
        $key = $this->getSnapshotKey($node);

        $changed = $this->snapshots[$key] ?? [];

        Debug::log('📷 Ending snapshot', [
            'key' => $key,
            'node' => get_class($node),
            'changed' => $changed,
        ], level: 3);

        array_pop($this->activeSnapshots);
        unset($this->snapshots[$key]);

        return $changed;
    }

    public function markSnapShotAsTerminated(NodeAbstract $node): void
    {
        $activeSnapshot = $this->getActiveSnapshotKey();

        if (! $activeSnapshot || ! array_key_exists($activeSnapshot, $this->snapshots)) {
            // TODO: Hmmmm... how do we have an active snapshot and it's not in the snapshots?
            return;
        }

        [$line, $tokenPos] = explode(':', $activeSnapshot);

        foreach ($this->snapshots[$activeSnapshot] as $changes) {
            foreach ($changes as $state) {
                $state->terminate($node->getEndLine());
            }
        }

        $this->endSnapshotAndCapture(new ShimmedNode($line, $tokenPos, 0, 0, $node->getStartLine()));
    }

    public function endSnapshotAndCapture(NodeAbstract $node): void
    {
        $changed = [$this->endSnapshot($node)];

        $finalChanged = [];

        foreach ($changed as $changes) {
            foreach ($changes as $name => $changes) {
                $finalChanged[$name] ??= [];
                $finalChanged[$name] = array_merge($finalChanged[$name], $changes);
            }
        }

        foreach ($finalChanged as $name => $changes) {
            $this->addTypes($name, $node, $changes);
        }
    }

    protected function addTypes(string $name, NodeAbstract $node, array $states): void
    {
        try {
            if ($previousValue = $this->getAtLine($name, $node)) {
                array_unshift($states, $previousValue);
            }
        } catch (InvalidArgumentException $e) {
            // No previous type found, probably a variable that was defined within the if statement
        }

        $lastState = $states[count($states) - 1];
        $terminatedAt = $lastState->terminatedAt();

        $newState = $this->add(
            $name,
            Type::union(...array_map(fn ($state) => $state->type(), $states)),
            $node,
        );

        if ($terminatedAt) {
            $newState->terminate($terminatedAt);
        }
    }
}
