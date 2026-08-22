<?php

use Laravel\Surveyor\Analysis\Condition;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node\Expr\Variable;

it('preserves a non-union type when removing a different type', function () {
    $originalType = Type::string();

    $condition = Condition::from(new Variable('value'), $originalType)
        ->removeType(Type::int());

    expect($condition->type)->toBe($originalType);
});
