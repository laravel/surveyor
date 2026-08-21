<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use Laravel\Surveyor\Concerns\LazilyLoadsDependencies;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
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
