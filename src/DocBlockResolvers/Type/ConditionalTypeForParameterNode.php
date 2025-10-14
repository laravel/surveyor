<?php

namespace Laravel\Surveyor\DocBlockResolvers\Type;

use Illuminate\Support\Arr;
use Laravel\Surveyor\Concerns\LazilyLoadsDependencies;
use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Support\Util;
use Laravel\Surveyor\Types\Type;
use PHPStan\PhpDocParser\Ast;

class ConditionalTypeForParameterNode extends AbstractResolver
{
    use LazilyLoadsDependencies;

    public function resolve(Ast\Type\ConditionalTypeForParameterNode $node)
    {
        $arg = $this->getArgForConditional($node);

        $argType = $arg ? $this->getNodeResolver()->from($arg->value, $this->scope) : Type::null();

        $targetType = $this->from($node->targetType);

        if ($targetType === 'class-string' && Util::isClassOrInterface($argType)) {
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
