<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Stringable;
use Laravel\StaticAnalyzer\Types\ClassType;
use Laravel\StaticAnalyzer\Types\Entities\View;
use Laravel\StaticAnalyzer\Types\MixedType;
use Laravel\StaticAnalyzer\Types\StringType;
use Laravel\StaticAnalyzer\Types\UnionType;

describe('ReturnTypeAnalyzer', function () {
    it('analyzes method with declared return type', function () {
        $result = analyzeFile('app/Http/Controllers/UserController.php');

        $methodReturnTypes = $result->methodReturnType(UserController::class, 'index');

        $this->assertCount(2, $methodReturnTypes);
        $this->assertInstanceOf(View::class, $methodReturnTypes[0]);
        $this->assertEquals('users.empty', $methodReturnTypes[0]->name);

        $this->assertArrayHasKey('users', $methodReturnTypes[0]->data);
        $this->assertInstanceOf(ClassType::class, $methodReturnTypes[0]->data['users']);
        $this->assertEquals(LengthAwarePaginator::class, $methodReturnTypes[0]->data['users']->value);

        $this->assertArrayHasKey('whatever', $methodReturnTypes[0]->data);
        $this->assertInstanceOf(StringType::class, $methodReturnTypes[0]->data['whatever']);
        $this->assertEquals('third', $methodReturnTypes[0]->data['whatever']->value);

        $this->assertInstanceOf(View::class, $methodReturnTypes[1]);
        $this->assertEquals('users.index', $methodReturnTypes[1]->name);

        $this->assertInstanceOf(ClassType::class, $methodReturnTypes[1]->data['users']);
        $this->assertEquals(LengthAwarePaginator::class, $methodReturnTypes[1]->data['users']->value);

        $this->assertArrayHasKey('whatever', $methodReturnTypes[1]->data);
        $this->assertInstanceOf(UnionType::class, $methodReturnTypes[1]->data['whatever']);
        $this->assertCount(3, $methodReturnTypes[1]->data['whatever']->types);

        $this->assertInstanceOf(StringType::class, $methodReturnTypes[1]->data['whatever']->types[0]);
        $this->assertEquals('first', $methodReturnTypes[1]->data['whatever']->types[0]->value);

        $this->assertInstanceOf(StringType::class, $methodReturnTypes[1]->data['whatever']->types[1]);
        $this->assertEquals('second', $methodReturnTypes[1]->data['whatever']->types[1]->value);

        $this->assertInstanceOf(StringType::class, $methodReturnTypes[1]->data['whatever']->types[2]);
        $this->assertEquals('fourth', $methodReturnTypes[1]->data['whatever']->types[2]->value);
    });

    it('analyzes method with array declared return type', function () {
        $result = analyzeFile('app/Http/Controllers/UserController.php');

        $methodReturnTypes = $result->methodReturnType(UserController::class, 'builder');

        $this->assertCount(1, $methodReturnTypes);
        $this->assertInstanceOf(View::class, $methodReturnTypes[0]);
        $this->assertEquals('users.update', $methodReturnTypes[0]->name);

        $this->assertArrayHasKey('company', $methodReturnTypes[0]->data);
        $this->assertInstanceOf(StringType::class, $methodReturnTypes[0]->data['company']);
        $this->assertEquals('Acme', $methodReturnTypes[0]->data['company']->value);
        $this->assertFalse($methodReturnTypes[0]->data['company']->isOptional());

        $this->assertArrayHasKey('name', $methodReturnTypes[0]->data);
        $this->assertInstanceOf(ClassType::class, $methodReturnTypes[0]->data['name']);
        $this->assertEquals(Stringable::class, $methodReturnTypes[0]->data['name']->value);
        $this->assertTrue($methodReturnTypes[0]->data['name']->isOptional());

        $this->assertArrayHasKey('email', $methodReturnTypes[0]->data);
        $this->assertInstanceOf(MixedType::class, $methodReturnTypes[0]->data['email']);
        $this->assertTrue($methodReturnTypes[0]->data['email']->isOptional());
    });
});
