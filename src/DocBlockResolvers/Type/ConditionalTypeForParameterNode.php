<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Resolvers\NodeResolver;
use PHPStan\PhpDocParser\Ast;
use Laravel\StaticAnalyzer\Types\Type;

class ConditionalTypeForParameterNode extends AbstractResolver
{
    public function resolve(Ast\Type\ConditionalTypeForParameterNode $node)
    {
        $arg = $this->getArgForConditional($node);

        // TODO: Is this correct and if so should we use Container?
        $argType = $arg ? app(NodeResolver::class)->from($arg->value) : Type::null();

        $targetType = $this->from($node->targetType);

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

        $arg = collect($this->referenceNode->getArgs())->first(fn($arg) => $arg->name?->name === $paramName);

        if ($arg) {
            return $arg;
        }

        $index = collect($this->parsed->getParamTagValues())->search(fn($param) => $param->parameterName === $node->parameterName);

        if ($index === false) {
            return null;
        }

        return $this->referenceNode->getArgs()[$index] ?? null;
    }
}
