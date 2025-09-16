<?php

namespace Laravel\Surveyor\Result;

use InvalidArgumentException;
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
        $changed = $this->getChanged($type, $node);

        $this->variables[$name] ??= [];
        $this->variables[$name][] = $changed;

        if (count($this->activeSnapshots) > 0) {
            $activeSnapshot = $this->activeSnapshots[count($this->activeSnapshots) - 1];
            $this->snapshots[$activeSnapshot][$name] ??= [];
            $this->snapshots[$activeSnapshot][$name][] = $changed;
        }
    }

    public function addManually(string $name, TypeContract $type, int $line, int $tokenPos, int $endLine, int $endTokenPos): void
    {
        $this->add(
            $name,
            $type,
            new class($line, $tokenPos, $endLine, $endTokenPos) extends NodeAbstract
            {
                public function __construct(
                    protected int $line,
                    protected int $tokenPos,
                    protected int $endLine,
                    protected int $endTokenPos,
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
            }
        );
    }

    protected function getChanged(TypeContract $type, NodeAbstract $node): array
    {
        return [
            'type' => $type,
            'startLine' => $node->getStartLine(),
            'endLine' => $node->getEndLine(),
            'startTokenPos' => $node->getStartTokenPos(),
            'endTokenPos' => $node->getEndTokenPos(),
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
        $this->variables[$name] ??= [];

        $lastValue = $this->variables[$name][count($this->variables[$name]) - 1] ?? null;
        $newType = $this->resolveArrayKeyType($lastValue, $key, $type);
        $changed = $this->getChanged($newType, $node);

        $this->variables[$name][] = $changed;

        if (count($this->activeSnapshots) > 0) {
            $activeSnapshot = $this->activeSnapshots[count($this->activeSnapshots) - 1];
            $this->snapshots[$activeSnapshot][$name] ??= [];
            $this->snapshots[$activeSnapshot][$name][] = $changed;
        }
    }

    public function get(string $name): ?TypeContract
    {
        return $this->variables[$name][count($this->variables[$name]) - 1]['type'] ?? null;
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

        $lines = array_filter(
            $this->variables[$name],
            fn ($variable) => $variable['startLine'] <= $node->getStartLine()
                && $variable['startTokenPos'] <= $node->getStartTokenPos(),
        );

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

        $this->snapshots[$key] = [];
        $this->activeSnapshots[] = $key;
    }

    public function endSnapshot(NodeAbstract $node): array
    {
        $key = $this->getSnapshotKey($node);

        $changed = $this->snapshots[$key] ?? [];

        array_pop($this->activeSnapshots);
        unset($this->snapshots[$key]);

        return $changed;
    }

    public function endSnapshotAndAddToPending(NodeAbstract $node): void
    {
        $this->addPendingType($node, $this->endSnapshot($node));
    }

    public function addPendingType(NodeAbstract $node, array $types): void
    {
        $key = $this->getSnapshotKey($node);

        $this->pendingTypes[$key] = $types;
    }

    public function getPendingTypes(NodeAbstract $node): array
    {
        $changed = [];

        foreach ($this->pendingTypes as $key => $types) {
            [$line, $tokenPos] = explode(':', $key);

            if (
                $line >= $node->getStartLine()
                && $line <= $node->getEndLine()
                && $tokenPos >= $node->getStartTokenPos()
                && $tokenPos <= $node->getEndTokenPos()
            ) {
                $changed[] = $types;
                unset($this->pendingTypes[$key]);
            }
        }

        return $changed;
    }
}
