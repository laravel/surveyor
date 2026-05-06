<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use Laravel\Surveyor\Concerns\LazilyLoadsDependencies;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\JsonApiResourceResponse;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PhpParser\Node;

trait ResolvesMethodCalls
{
    use AddsValidationRules, LazilyLoadsDependencies, ResolvesResourceConditionals;

    protected function resolveMethodCall(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node)
    {
        $var = $this->from($node->var);

        // Resource chaining (e.g. Resource::make($m)->additional(...)) — the call site's
        // var may resolve to a UnionType that contains a ResourceResponse alongside the
        // declared return type of make(). Unwrap it so chained Resource methods can see
        // the response shape.
        if ($var instanceof UnionType) {
            foreach ($var->types as $candidate) {
                if ($candidate instanceof ResourceResponse || $candidate instanceof JsonApiResourceResponse) {
                    $var = $candidate;
                    break;
                }
            }
        }

        if ($var instanceof MixedType || ! $var instanceof ClassType) {
            return Type::mixed();
        }

        $methodName = $this->from($node->name);

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

        if (
            $methodName->value === 'additional'
            && ($var instanceof ResourceResponse || $var instanceof JsonApiResourceResponse)
        ) {
            return $this->resolveResourceAdditional($var, $node);
        }

        return Type::union(
            ...$this->reflector->methodReturnType(
                $this->scope->getUse($var->value),
                $methodName->value,
                $node,
            ),
        );
    }

    protected function resolveResourceAdditional(
        ResourceResponse|JsonApiResourceResponse $response,
        Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node,
    ): ResourceResponse|JsonApiResourceResponse {
        $arg = isset($node->args[0]) ? $this->from($node->args[0]->value) : null;

        $merged = $arg instanceof ArrayType
            ? $this->mergeAdditional($response->additional, $arg)
            : $response->additional;

        if ($response instanceof ResourceResponse) {
            return new ResourceResponse(
                resourceClass: $response->resourceClass,
                data: $response->data,
                isCollection: $response->isCollection,
                wrap: $response->wrap,
                additional: $merged,
            );
        }

        return new JsonApiResourceResponse(
            resourceClass: $response->resourceClass,
            attributes: $response->attributes,
            relationships: $response->relationships,
            links: $response->links,
            meta: $response->meta,
            isCollection: $response->isCollection,
            additional: $merged,
        );
    }

    protected function mergeAdditional(?TypeContract $existing, ArrayType $incoming): ArrayType
    {
        if (! $existing instanceof ArrayType) {
            return $incoming;
        }

        return new ArrayType(array_merge($existing->value, $incoming->value));
    }
}
