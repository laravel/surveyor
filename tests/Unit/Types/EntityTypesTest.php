<?php

use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Entities\InertiaRender;
use Laravel\Surveyor\Types\Entities\View;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;

describe('View', function () {
    it('properly initializes parent ClassType value', function () {
        $view = new View('welcome', Type::array([]));

        expect($view->resolved())->toBe('Illuminate\View\View');
    });

    it('returns custom id based on view name and data', function () {
        $view = new View('welcome', Type::array([]));

        expect($view->id())->toBe('welcome::'.Type::array([])->id());
    });

    it('works correctly with Type::union', function () {
        $view = new View('welcome', Type::array([]));
        $classType = new ClassType('App\Models\User');

        $union = Type::union($view, $classType);

        expect($union)->toBeInstanceOf(UnionType::class);
    });
});

describe('InertiaRender', function () {
    it('properly initializes parent ClassType value', function () {
        $inertia = new InertiaRender('Dashboard', Type::array([]));

        expect($inertia->resolved())->toBe('Inertia\Response');
    });

    it('returns custom id based on view name and data', function () {
        $inertia = new InertiaRender('Dashboard', Type::array([]));

        expect($inertia->id())->toBe('Dashboard::'.Type::array([])->id());
    });

    it('works correctly with Type::union', function () {
        $inertia = new InertiaRender('Dashboard', Type::array([]));
        $classType = new ClassType('App\Models\User');

        $union = Type::union($inertia, $classType);

        expect($union)->toBeInstanceOf(UnionType::class);
    });
});
