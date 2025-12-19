<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Validation\ValidationRuleParser;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\StringType;

trait AddsValidationRules
{
    protected function addValidationRules($rulesArg)
    {
        $rules = $this->from($rulesArg);

        foreach ($rules->value as $key => $value) {
            switch (true) {
                case $value instanceof StringType:
                    $this->scope->result()->addValidationRule(
                        $key,
                        array_map(fn ($subRule) => ValidationRuleParser::parse($subRule), explode('|', $value->value)),
                    );
                    break;
                case $value instanceof ArrayType:
                    $this->scope->result()->addValidationRule(
                        $key,
                        array_values(
                            array_filter(
                                array_map(
                                    fn ($subRule) => property_exists($subRule, 'value') ? ValidationRuleParser::parse($subRule->value) : null,
                                    $value->value,
                                ),
                            ),
                        ),
                    );
                    break;
            }
        }
    }
}
