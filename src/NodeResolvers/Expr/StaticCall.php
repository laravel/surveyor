<?php

namespace Laravel\Surveyor\NodeResolvers\Expr;

use Laravel\Surveyor\NodeResolvers\AbstractResolver;
use Laravel\Surveyor\Support\Util;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\MultiType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PhpParser\Node;

class StaticCall extends AbstractResolver
{
    public function resolve(Node\Expr\StaticCall $node)
    {
        $class = $this->from($node->class);
        $method = $node->name instanceof Node\Identifier ? $node->name->name : $this->from($node->name);

        if ($class instanceof UnionType) {
            $class = $this->resolveUnion($class);
        }

        if ($class instanceof StringType) {
            return ($class->value === null) ? null : Type::mixed();
        }

        if ($method instanceof MultiType) {
            $returnTypes = [];

            foreach ($method->types as $type) {
                $returnTypes = array_merge(
                    $returnTypes,
                    $this->reflector->methodReturnType($class, $type->value, $node),
                );
            }

            return Type::union(...$returnTypes);
        }

        $returnTypes = $this->reflector->methodReturnType($class, $method, $node);

        return Type::union(...$returnTypes);
    }

    public function resolveForCondition(Node\Expr\StaticCall $node)
    {
        return $this->fromOutsideOfCondition($node);
    }

    protected function resolveUnion(UnionType $union)
    {
        foreach ($union->types as $type) {
            if ($type instanceof ClassType) {
                return $type;
            }

            if ($type instanceof StringType) {
                if (Util::isClassOrInterface($type->value)) {
                    return new ClassType($type->value);
                }

                $templateTag = $this->scope->getTemplateTag($type->value);

                if ($templateTag) {
                    return $templateTag->bound;
                }
            }
        }

        return null;
    }
}
