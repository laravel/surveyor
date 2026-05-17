<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Http\Request;
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

        $methodName = $node->name instanceof Node\Identifier
            ? new StringType($node->name->name)
            : $this->from($node->name);

        if (! Type::is($methodName, StringType::class) || $methodName->value === null) {
            // Method names that happen to match PHP function names resolve as ClassType
            // due to Util::isClassOrInterface(). Handle resource conditionals here before
            // returning mixed, since methods like when() collide with Laravel's when() helper.
            if (
                $methodName instanceof ClassType
                && $methodName->value !== null
                && in_array($methodName->value, static::$conditionalMethods)
                && $this->isJsonResource($var)
            ) {
                return $this->resolveResourceConditional($var, $methodName->value, $node);
            }

            return Type::mixed();
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

        return Type::union(
            ...$this->reflector->methodReturnType(
                $this->scope->getUse($var->value),
                $methodName->value,
                $node,
            ),
        );
    }
}
