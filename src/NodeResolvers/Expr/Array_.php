<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

class Array_ extends AbstractResolver
{
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

        foreach ($node->items as $item) {
            if ($item === null) {
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

            $result[] = $this->from($item->value);
        }

        return Type::array($result);
    }

    protected function resolveKeyedArray(Node\Expr\Array_ $node)
    {
        $result = [];

        foreach ($node->items as $item) {
            if ($item === null) {
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

            $result[$item->key->value ?? null] = $this->from($item->value);
        }

        return Type::array($result);
    }
}
