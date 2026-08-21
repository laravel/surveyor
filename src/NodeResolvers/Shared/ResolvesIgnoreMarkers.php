<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Laravel\Surveyor\Analyzed\IgnoreMarker;
use Laravel\Surveyor\Support\Markers;
use PhpParser\Node;

trait ResolvesIgnoreMarkers
{
    protected function ignoreMarker(Node $node): ?IgnoreMarker
    {
        foreach ($node->attrGroups ?? [] as $group) {
            foreach ($group->attrs as $attribute) {
                foreach ($this->resolveAttributeNames($attribute) as $name) {
                    if (! Markers::isIgnoreAttribute($name)) {
                        continue;
                    }

                    if (! Markers::acceptsConditions($name)) {
                        return new IgnoreMarker;
                    }

                    return new IgnoreMarker(
                        $this->resolveIgnoreCondition($attribute, $name, 'unless'),
                        $this->resolveIgnoreCondition($attribute, $name, 'when'),
                    );
                }
            }
        }

        return $this->hasIgnoreTag($node) ? new IgnoreMarker : null;
    }

    /**
     * Read one of the conditions an attribute was written with. Attribute
     * arguments are constant expressions, so the value is in the file: nothing
     * is called, and nothing is loaded, to find it.
     */
    protected function resolveIgnoreCondition(Node\Attribute $attribute, string $class, string $name): string|array|null
    {
        foreach ($attribute->args as $index => $argument) {
            $argumentName = $argument->name?->toString()
                ?? Markers::constructorParameterName($class, $index);

            if ($argumentName === $name) {
                return $this->constantValue($argument->value);
            }
        }

        return null;
    }

    /**
     * An expression this does not understand comes back as null, which leaves
     * the declaration hidden either way.
     */
    protected function constantValue(Node\Expr $expression): string|array|null
    {
        if ($expression instanceof Node\Scalar\String_) {
            return $expression->value;
        }

        if ($expression instanceof Node\Expr\ClassConstFetch
            && $expression->name instanceof Node\Identifier
            && $expression->name->toString() === 'class'
            && $expression->class instanceof Node\Name) {
            return $expression->class instanceof Node\Name\FullyQualified
                ? $expression->class->toString()
                : $this->scope->getUse($expression->class->toString());
        }

        if ($expression instanceof Node\Expr\Array_) {
            $values = [];

            foreach ($expression->items as $item) {
                if ($item === null) {
                    continue;
                }

                $value = $this->constantValue($item->value);

                if ($value === null) {
                    return null;
                }

                $values[] = $value;
            }

            return $values;
        }

        return null;
    }

    /**
     * Only a doc block counts on a declaration. A trailing line comment is
     * handed by the parser to whatever declaration comes next, so honoring one
     * would hide a neighbour instead of the member the author was looking at.
     */
    protected function hasIgnoreTag(Node $node): bool
    {
        $docBlock = $node->getDocComment();

        return $docBlock !== null && Markers::commentHasIgnoreTag($docBlock->getText());
    }

    /**
     * Work out which items of an array literal carry an ignore marker, by
     * position rather than by what the parser attached the comment to.
     *
     * @return array<int, true>
     */
    protected function ignoredArrayItems(Node\Expr\Array_ $node): array
    {
        $markers = $this->scope->ignoreMarkerComments();

        if ($markers === []) {
            return [];
        }

        $items = [];

        foreach ($node->items as $index => $item) {
            if ($item !== null) {
                $items[$index] = $item;
            }
        }

        $ignored = [];

        foreach ($markers as $marker) {
            if ($marker['pos'] < $node->getStartFilePos() || $marker['pos'] > $node->getEndFilePos()) {
                continue;
            }

            $owner = $this->markerOwner($items, $marker);

            if ($owner !== null) {
                $ignored[$owner] = true;
            }
        }

        return $ignored;
    }

    /**
     * @param  array<int, Node\ArrayItem>  $items
     * @param  array{pos: int, line: int}  $marker
     */
    protected function markerOwner(array $items, array $marker): ?int
    {
        $previous = null;
        $next = null;

        foreach ($items as $index => $item) {
            if ($marker['pos'] >= $item->getStartFilePos() && $marker['pos'] <= $item->getEndFilePos()) {
                // The marker sits inside an item, so it belongs to an array
                // nested in this one and is resolved at that level instead.
                return null;
            }

            if ($item->getEndFilePos() < $marker['pos']) {
                $previous = $index;

                continue;
            }

            $next ??= $index;
        }

        // A marker left at the end of a line belongs to the item on that line,
        // unless another item follows it there.
        if ($previous !== null
            && $items[$previous]->getEndLine() === $marker['line']
            && ($next === null || $items[$next]->getStartLine() > $marker['line'])) {
            return $previous;
        }

        return $next;
    }

    /**
     * A short name can reach its class through an import, an alias, or the
     * current namespace, so hand back every candidate rather than guessing.
     *
     * @return list<string>
     */
    protected function resolveAttributeNames(Node\Attribute $attribute): array
    {
        $written = $attribute->name->toString();

        if ($attribute->name instanceof Node\Name\FullyQualified) {
            return [$written];
        }

        return array_values(array_unique([
            $written,
            $this->scope->getUse($written),
            $this->scope->resolveBuggyUse($written),
        ]));
    }
}
