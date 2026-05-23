<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Result\VariableState;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
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

            $result[] = $this->resolveItemValue($item);
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

            $result[$item->key->value ?? null] = $this->resolveItemValue($item);
        }

        return Type::array($result);
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
