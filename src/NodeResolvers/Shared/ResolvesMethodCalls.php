<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request as RequestFacade;
use Laravel\Surveyor\Concerns\LazilyLoadsDependencies;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

trait ResolvesMethodCalls
{
    use AddsValidationRules, LazilyLoadsDependencies, ResolvesResourceConditionals;

    protected function resolveMethodCall(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node)
    {
        $var = $this->from($node->var);

        if ($var instanceof MixedType || ! $var instanceof ClassType) {
            return Type::mixed();
        }

        $methodName = $this->from($node->name);

        if (! Type::is($methodName, StringType::class) || $methodName->value === null) {
            // Method names that happen to match PHP function/class names resolve as ClassType
            // due to Util::isClassOrInterface(). If the value has no namespace separator it's
            // a simple identifier mis-identified as a class; treat it as the method name.
            if ($methodName instanceof ClassType && $methodName->value !== null && ! str_contains($methodName->value, '\\')) {
                if (in_array($methodName->value, static::$conditionalMethods) && $this->isJsonResource($var)) {
                    return $this->resolveResourceConditional($var, $methodName->value, $node);
                }

                $methodName = Type::string($methodName->value);
            } else {
                return Type::mixed();
            }
        }

        switch ($var->value) {
            case Request::class:
            case RequestFacade::class:
                if ($methodName->value === 'validate') {
                    $this->addValidationRules($node->args[0]->value);
                }

                if ($methodName->value === 'user' && $requestUserType = $this->getResolver()->requestUserType()) {
                    return $requestUserType;
                }
                break;
        }

        if (in_array($methodName->value, static::$conditionalMethods) && $this->isJsonResource($var)) {
            return $this->resolveResourceConditional($var, $methodName->value, $node);
        }

        if (
            $methodName->value === 'toArray'
            && count($var->genericTypes()) >= 2
            && is_a($this->scope->getUse($var->value), Collection::class, true)
        ) {
            $genericTypes = array_values($var->genericTypes());

            return Type::arrayShape($genericTypes[0], $genericTypes[1]);
        }

        return Type::union(
            ...$this->reflector->methodReturnType(
                $this->scope->getUse($var->value),
                $methodName->value,
                $node,
            ),
        );
    }
}
