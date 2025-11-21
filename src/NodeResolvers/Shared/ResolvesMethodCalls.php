<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationRuleParser;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\MixedType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;

trait ResolvesMethodCalls
{
    protected function resolveMethodCall(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node)
    {
        $var = $this->from($node->var);

        if ($var instanceof MixedType || ! $var instanceof ClassType) {
            return Type::mixed();
        }

        $methodName = $this->from($node->name);

        if (! Type::is($methodName, StringType::class) || $methodName->value === null) {
            return Type::mixed();
        }

        if ($var->value === Request::class && $methodName->value === 'validate') {
            $this->addValidationRules($node->args[0]->value);
        }

        return Type::union(
            ...$this->reflector->methodReturnType(
                $this->scope->getUse($var->value),
                $methodName->value,
                $node,
            ),
        );
    }

    protected function addValidationRules($rulesArg)
    {
        $rules = $this->from($rulesArg);

        foreach ($rules->value as $key => $value) {
            if ($value instanceof StringType) {
                $this->scope->addValidationRule(
                    $key,
                    array_map(
                        fn ($subRule) => ValidationRuleParser::parse($subRule),
                        explode('|', $value->value),
                    ),
                );
            } elseif ($value instanceof ArrayType) {
                $this->scope->addValidationRule(
                    $key,
                    array_map(fn ($subRule) => $this->from($subRule), $value->value),
                );
            } else {
                // dump([$key, $value, $rulesArg]);
            }
        }
    }
}
