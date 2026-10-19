<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesIgnoreMarkers;
use Laravel\Surveyor\Result\VariableState;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\ResourceMerge;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Array_ extends AbstractResolver
{
    use ResolvesIgnoreMarkers;

    public function resolve(Node\Expr\Array_ $node)
    {
        if ($this->isListArray($node)) {
            return $this->resolveListArray($node);
        }

        return $this->resolveKeyedArray($node);
    }

    protected function isListArray(Node\Expr\Array_ $node): bool
    {
        foreach ($node->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->unpack) {
                $spreadValue = $this->from($item->value);

                if ($spreadValue instanceof ArrayType && ! $spreadValue->isList()) {
                    return false;
                }

                continue;
            }

            if ($item->key !== null) {
                return false;
            }
        }

        return true;
    }

    protected function resolveListArray(Node\Expr\Array_ $node)
    {
        $result = [];
        $ignored = $this->ignoredArrayItems($node);

        foreach ($node->items as $index => $item) {
            if ($item === null || isset($ignored[$index])) {
                continue;
            }

            if ($item->unpack) {
                $spreadValue = $this->from($item->value);

                if ($spreadValue instanceof ArrayType) {
                    foreach ($spreadValue->value as $value) {
                        $result[] = $value;
                    }
                }

                continue;
            }

            $result[] = $this->resolveItemValue($item);
        }

        return Type::array($this->mergeResourceValues($result));
    }

    protected function resolveKeyedArray(Node\Expr\Array_ $node)
    {
        $result = [];
        $ignored = $this->ignoredArrayItems($node);

        foreach ($node->items as $index => $item) {
            if ($item === null || isset($ignored[$index])) {
                continue;
            }

            if ($item->unpack) {
                $spreadValue = $this->from($item->value);

                if ($spreadValue instanceof ArrayType) {
                    foreach ($spreadValue->value as $key => $value) {
                        $result[$key] = $value;
                    }
                }

                continue;
            }

            $value = $this->resolveItemValue($item);

            if ($item->key === null) {
                $result[] = $value;
            } else {
                $key = $item->key->value ?? ($this->from($item->key)->value ?? null);

                if (is_scalar($key)) {
                    $result[$key] = $value;
                }
            }
        }

        return Type::array($this->mergeResourceValues($result));
    }

    /**
     * Flatten the first resource merge at a numeric key into the array the way
     * Laravel's resource filter() does: a list is spliced in, while keyed data
     * gives way to earlier keys and takes precedence over later ones. A later
     * key still fills in for a merged key that may be missing.
     */
    protected function mergeResourceValues(array $values): array
    {
        $index = 0;

        foreach ($values as $key => $value) {
            if (is_numeric($key) && $value instanceof ResourceMerge) {
                $before = array_slice($values, 0, $index, true);
                $after = $this->mergeResourceValues(array_slice($values, $index + 1, null, true));

                if ($value->isList()) {
                    return array_merge($before, $value->value, $after);
                }

                $merged = $value->value;

                foreach ($merged as $mergedKey => $type) {
                    if ($type->isOptional() && isset($after[$mergedKey])) {
                        $fallback = $after[$mergedKey];
                        $merged[$mergedKey] = (clone Type::union($type, $fallback))->required(! $fallback->isOptional());
                    }
                }

                return $before + $merged + $after;
            }

            $index++;
        }

        return $values;
    }

    protected function resolveItemValue(Node\ArrayItem $item): TypeContract
    {
        if ($comment = $item->getDocComment()) {
            if ($type = $this->docBlockParser->parseVar($comment->getText())) {
                return $type;
            }
        }

        $type = $this->from($item->value);

        return match (true) {
            $type instanceof VariableState => $type->type(),
            $type instanceof TypeContract => $type,
            default => Type::mixed(),
        };
    }
}
