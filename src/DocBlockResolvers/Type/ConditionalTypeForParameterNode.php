<?php

namespace Laravel\Surveyor\DocBlockResolvers\Type;

use Illuminate\Support\Arr;
use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Resolvers\NodeResolver;
use Laravel\Surveyor\Types\GenericObjectType;
use Laravel\Surveyor\Types\Type;
use PHPStan\PhpDocParser\Ast;

class ConditionalTypeForParameterNode extends AbstractResolver
{
    public function resolve(Ast\Type\ConditionalTypeForParameterNode $node)
    {
        $arg = $this->getArgForConditional($node);

        $argType = $arg ? app(NodeResolver::class)->from($arg->value, $this->scope) : Type::null();

        $targetType = $this->from($node->targetType);

        if ($targetType instanceof GenericObjectType) {
            if ($targetType->base === 'class-string') {
                $returnTypeTarget = $node->negated ? $this->from($node->else) : $this->from($node->if);

                foreach ($targetType->types as $genericType) {
                    if (Type::isSame($returnTypeTarget, $genericType)) {
                        return $argType;
                    }
                }
            }
        }

        if ($targetType === 'class-string' && class_exists($argType)) {
            return $node->negated ? $this->from($node->else) : $this->from($node->if);
        }

        if (Type::isSame($argType, $targetType) && ! $node->negated) {
            return $this->from($node->if);
        }

        return $this->from($node->else);
    }

    protected function getArgForConditional(Ast\Type\ConditionalTypeForParameterNode $node): mixed
    {
        if (! $this->referenceNode) {
            return null;
        }

        $paramName = ltrim($node->parameterName, '$');

        $arg = Arr::first(
            $this->referenceNode->getArgs(),
            fn ($arg) => $arg->name?->name === $paramName,
        );

        if ($arg) {
            return $arg;
        }

        foreach ($this->parsed->getParamTagValues() as $index => $arg) {
            if ($arg->parameterName === $node->parameterName) {
                return $this->referenceNode->getArgs()[$index] ?? null;
            }
        }

        return null;
    }
}
