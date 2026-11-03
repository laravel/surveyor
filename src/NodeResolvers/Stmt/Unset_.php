<?php

namespace Laravel\Surveyor\NodeResolvers\Stmt;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\NodeResolvers\Shared\ResolvesArrays;
use Laravel\Surveyor\Types\Contracts\MultiType;
use Laravel\Surveyor\Types\StringType;
use PhpParser\Node;

class Unset_ extends AbstractResolver
{
    use ResolvesArrays;

    public function resolve(Node\Stmt\Unset_ $node)
    {
        $arrayUnsets = [];

        foreach ($node->vars as $var) {
            if (! $var instanceof Node\Expr\ArrayDimFetch) {
                $this->scope->state()->unset($var, $node);

                continue;
            }

            if ($var->dim === null) {
                continue;
            }

            $dim = $this->from($var->dim);

            if ($dim instanceof MultiType) {
                $dim = array_filter($dim->getTypes(), fn ($type) => $type instanceof StringType && $type->value !== null)[0] ?? null;

                if ($dim === null) {
                    continue;
                }
            }

            if (! property_exists($dim, 'value') || $dim->value === null) {
                // Couldn't figure out the dim, so we can't unset the array key
                continue;
            }

            [$root, $keys] = $this->resolveArrayVarAndKeys($var);

            $name = $root instanceof Node\Expr\PropertyFetch ? $root->name : ($root->name ?? null);
            $id = get_class($root).':'.(is_string($name) ? $name : ($name instanceof Node\Identifier ? $name->name : spl_object_id($root)));
            $arrayUnsets[$id] ??= [$root, []];
            $arrayUnsets[$id][1][] = $keys;
        }

        foreach ($arrayUnsets as [$root, $paths]) {
            $this->scope->state()->unsetArrayKeys($root, $paths, $node);
        }

        return null;
    }
}
