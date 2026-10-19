<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use Laravel\Surveyor\Concerns\LazilyLoadsDependencies;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use ReflectionMethod;
use ReflectionNamedType;

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

        if ($var instanceof ResourceResponse && ($response = $this->resolveResourceResponseCall($var, $methodName->value, $node))) {
            return $response;
        }

        $returned = Type::union(
            ...$this->reflector->methodReturnType(
                $var,
                $methodName->value,
                $node,
            ),
        );

        return $this->keepReceiver($var, $returned);
    }

    /**
     * Calls that leave a resource response describing the same JSON.
     * additional() and preserveKeys() change what is sent, while response(),
     * a collection's fluent methods such as preserveQuery(), and the fluent
     * methods of the JsonResponse it becomes pass it on unchanged.
     */
    protected function resolveResourceResponseCall(ResourceResponse $var, string $method, Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node): ?TypeContract
    {
        $args = $node->getArgs();

        if ($method === 'additional') {
            $additional = isset($args[0]) ? $this->from($args[0]->value) : null;

            return $additional instanceof ArrayType ? $var->withAdditional($additional) : $var;
        }

        if ($method === 'preserveKeys' && $var->isCollection) {
            $preserve = isset($args[0]) ? $this->from($args[0]->value) : null;

            return $var->withPreservedKeys(! ($preserve instanceof BoolType && $preserve->value === false));
        }

        if ($method === 'response' || $method === 'toResponse') {
            return $var;
        }

        foreach ([$var->responseClass(), JsonResponse::class] as $class) {
            if (method_exists($class, $method)) {
                return $this->returnsReceiver($class, $method) ? $var : null;
            }
        }

        return null;
    }

    /**
     * Whether a method is declared to return the object it was called on:
     * static or self, or a @return $this or static tag.
     */
    protected function returnsReceiver(string $class, string $method): bool
    {
        $reflection = new ReflectionMethod($class, $method);
        $returnType = $reflection->getReturnType();

        if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['static', 'self'])) {
            return true;
        }

        return (bool) preg_match('/@return\s+(\$this|static|self)\b/', $reflection->getDocComment() ?: '');
    }

    /**
     * A fluent method hands back the object it was called on. Reflection only
     * reports which class that is, so a receiver carrying more than a class
     * name, such as a resource response and the shape it produces, would come
     * back as a bare class and lose the shape.
     */
    protected function keepReceiver(ClassType $var, TypeContract $returned): TypeContract
    {
        if ($var::class === ClassType::class || $returned::class !== ClassType::class) {
            return $returned;
        }

        if ($returned->resolved() !== $var->resolved()) {
            return $returned;
        }

        return $returned->isNullable() && ! $var->isNullable()
            ? (clone $var)->nullable()
            : $var;
    }
}
