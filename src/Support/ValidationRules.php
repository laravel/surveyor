<?php

namespace Laravel\Surveyor\Support;

use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationRuleParser;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;

class ValidationRules
{
    /**
     * Rule objects whose only meaning for a type is the string rule they stand for.
     */
    protected const RULE_OBJECTS = [
        Rules\ImageFile::class => 'Image',
        Rules\File::class => 'File',
        Rules\Email::class => 'Email',
        Rules\Numeric::class => 'Numeric',
        Rules\Date::class => 'Date',
        Rules\Password::class => 'String',
    ];

    /**
     * Turn one statically resolved rule into Laravel's parsed [rule, params]
     * form, or null when it says nothing about the field's type.
     */
    public static function parse(?TypeContract $rule): ?array
    {
        if ($rule instanceof StringType) {
            return $rule->value === null ? null : ValidationRuleParser::parse($rule->value);
        }

        if (! $rule instanceof ClassType) {
            return null;
        }

        $arguments = array_values($rule->constructorArguments());

        if ($rule->value === Rules\Enum::class) {
            $enum = Type::stringValue($arguments[0] ?? null);

            return $enum !== null && enum_exists($enum) ? [new Rules\Enum($enum), []] : null;
        }

        if ($rule->value === Rules\In::class) {
            return self::inRule($arguments[0] ?? null);
        }

        foreach (self::RULE_OBJECTS as $class => $name) {
            if (is_a($rule->value, $class, true)) {
                return [$name, []];
            }
        }

        return null;
    }

    protected static function inRule(?TypeContract $values): ?array
    {
        if (! $values instanceof ArrayType) {
            return null;
        }

        $literals = [];

        foreach ($values->value as $value) {
            $literal = $value instanceof TypeContract && property_exists($value, 'value') ? $value->value : null;

            if (! is_scalar($literal)) {
                return null;
            }

            $literals[] = (string) $literal;
        }

        return $literals === [] ? null : ['In', $literals];
    }
}
