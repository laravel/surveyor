<?php

namespace Laravel\Surveyor\Result;

use InvalidArgumentException;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Support\ShimmedNode;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PhpParser\NodeAbstract;
use Throwable;

class StateTrackerItem
{
    protected array $variables = [];

    protected array $snapshots = [];

    protected array $activeSnapshots = [];

    protected array $pendingTypes = [];

    public function add(string $name, TypeContract $type, NodeAbstract $node): void
    {
        $this->updateSnapshotOrVariable($name, $this->getAttributes($type, $node));
    }

    public function getActiveSnapshotKey(): ?string
    {
        return $this->activeSnapshots[count($this->activeSnapshots) - 1] ?? null;
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

    protected function getAttributes(TypeContract $type, NodeAbstract $node): array
    {
        return [
            'type' => $type,
            'startLine' => $node->getStartLine(),
            'endLine' => $node->getEndLine(),
            'startTokenPos' => $node->getStartTokenPos(),
            'endTokenPos' => $node->getEndTokenPos(),
            'terminatedAt' => $node instanceof ShimmedNode ? $node->terminatedAt() : null,
        ];
    }

    public function narrow(string $name, TypeContract $type, NodeAbstract $node): void
    {
        $currentType = $this->getAtLine($name, $node)['type'];

        if (Type::is($currentType, $type)) {
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

    public function unsetArrayKey(string $name, string $key, NodeAbstract $node): void
    {
        $this->updateArrayKey($name, $key, Type::null(), $node);
    }

    public function removeType(string $name, NodeAbstract $node, TypeContract $type): void
    {
        $currentType = $this->getAtLine($name, $node)['type'];

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

    public function removeArrayKeyType(string $name, string $key, TypeContract $type, NodeAbstract $node): void
    {
        // TODO: Implement
    }

    public function updateArrayKey(string $name, string $key, TypeContract $type, NodeAbstract $node): void
    {
        $lastValue = $this->getLastSnapshotValue($name) ?? $this->getLastValue($name);
        $newType = $this->resolveArrayKeyType($lastValue, $key, $type);
        $changed = $this->getAttributes($newType, $node);

        $this->updateSnapshotOrVariable($name, $changed);
    }

    protected function updateSnapshotOrVariable(string $name, array $changed): void
    {
        $activeSnapshot = $this->getActiveSnapshotKey();

        if ($activeSnapshot) {
            Debug::log('🆕 Updating snapshot', [
                'name' => $name,
                'changes' => $changed,
                'snapshot' => $activeSnapshot,
            ], level: 2);

            $this->snapshots[$activeSnapshot][$name] ??= [];
            $this->snapshots[$activeSnapshot][$name][] = $changed;
        } else {
            Debug::log('🆕 Updating variable', [
                'name' => $name,
                'changes' => $changed,
            ], level: 2);

            $this->variables[$name] ??= [];
            $this->variables[$name][] = $changed;
        }
    }

    public function getLastSnapshotValue(string $name): ?array
    {
        $activeSnapshot = $this->getActiveSnapshotKey();

        if (! $activeSnapshot) {
            return null;
        }

        $values = $this->snapshots[$activeSnapshot][$name] ?? [];

        return $values[count($values) - 1] ?? null;
    }

    public function getLastValue(string $name): ?array
    {
        return $this->variables[$name][count($this->variables[$name]) - 1] ?? null;
    }

    public function get(string $name): ?TypeContract
    {
        return $this->getLastValue($name)['type'] ?? null;
    }

    protected function resolveArrayKeyType(?array $lastValue, string $key, TypeContract $type): TypeContract
    {
        if ($lastValue === null) {
            return new ArrayType([$key => $type]);
        }

        if ($lastValue['type'] instanceof ArrayType) {
            return new ArrayType(array_merge($lastValue['type']->value, [$key => $type]));
        }

        if ($lastValue['type'] instanceof UnionType) {
            $existingTypes = $lastValue['type']->types;

            try {
                return new UnionType(
                    array_map(fn ($t) => new ArrayType(array_merge($t->value, [$key => $type])), $existingTypes)
                );
            } catch (Throwable $e) {
                dd('t->value is null??', $key, $type, $existingTypes, $this, $e->getMessage());
            }
        }

        dd('last value is not an array or union type??', $lastValue);
    }

    public function getAtLine(string $name, NodeAbstract $node): array
    {
        if (! array_key_exists($name, $this->variables)) {
            return [];
        }

        Debug::interested($node->getStartLine() === 52);

        $lines = array_filter(
            $this->variables[$name],
            fn ($variable) => $variable['startLine'] <= $node->getStartLine()
                && $variable['startTokenPos'] <= $node->getStartTokenPos()
                && ($variable['terminatedAt'] === null || $variable['terminatedAt'] >= $node->getStartLine()),
        );

        Debug::dumpIfInterested($lines);

        $result = end($lines);

        if ($result === false) {
            throw new InvalidArgumentException(
                'No result found for '.$name.' at line '.$node->getStartLine().' and position '.$node->getStartTokenPos(),
            );
        }

        if ($result['startLine'] !== $node->getStartLine()) {
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
        ], level: 2);

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
        ], level: 2);

        array_pop($this->activeSnapshots);
        unset($this->snapshots[$key]);

        return $changed;
    }

    public function markSnapShotAsTerminated(NodeAbstract $node): void
    {
        $activeSnapshot = $this->getActiveSnapshotKey();

        if (! $activeSnapshot) {
            return;
        }

        [$line, $tokenPos] = explode(':', $activeSnapshot);

        foreach ($this->snapshots[$activeSnapshot] as $name => $changes) {
            foreach ($changes as $index => $_) {
                $this->snapshots[$activeSnapshot][$name][$index]['terminatedAt'] = $node->getStartLine();
            }
        }

        $this->endSnapshotAndAddToPending(new ShimmedNode($line, $tokenPos, 0, 0, $node->getStartLine()));
    }

    public function endSnapshotAndAddToPending(NodeAbstract $node): void
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
            $types = [];

            foreach ($changes as $change) {
                // if (($change['terminatedAt'] ?? -1) > 0) {
                //     $this->addTypes($name, $node, [...$types, $change['type']]);
                // } else {
                $types[] = $change['type'];
                // }
            }

            $this->addTypes($name, $node, $types);
        }
    }

    protected function addTypes(string $name, NodeAbstract $node, array $types): void
    {
        try {
            dump($this->getAtLine($name, $node));
            array_unshift($types, $this->getAtLine($name, $node)['type']);
        } catch (InvalidArgumentException $e) {
            // No previous type found, probably a variable that was defined within the if statement
        }

        $this->add($name, Type::union(...$types), $node);
    }

    public function addPendingType(NodeAbstract $node, array $types): void
    {
        $key = $this->getSnapshotKey($node);

        $this->pendingTypes[$key] = $types;
    }

    public function getPendingTypes(NodeAbstract $node): array
    {
        $key = $this->getSnapshotKey($node);
        $pending = $this->pendingTypes[$key] ?? [];

        unset($this->pendingTypes[$key]);

        return [$pending];
    }
}
