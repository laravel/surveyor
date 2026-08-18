<?php

use App\Support\CaseProbe;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Surveyor\Support\Util;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;

uses()->group('integration');

describe('Util::isClassOrInterface', function () {
    it('accepts a class written the way it is declared', function () {
        expect(Util::isClassOrInterface(Request::class))->toBeTrue();
        expect(Util::isClassOrInterface(Str::class))->toBeTrue();
        expect(Util::isClassOrInterface(Util::class))->toBeTrue();
    });

    it('tells an alias apart from a differently cased spelling of it', function () {
        if (! class_exists('CaseProbe', false)) {
            class_alias(CaseProbe::class, 'CaseProbe');
        }

        // PHP finds a class whatever the case, so without this every string
        // spelled like a class name becomes one.
        expect(Util::isClassOrInterface('CaseProbe'))->toBeTrue();
        expect(Util::isClassOrInterface('caseprobe'))->toBeFalse();
    });

    it('rejects a bare literal that collides with a facade alias', function () {
        // The case that reached generated output: 'request' found the Request
        // facade, resolved to Illuminate\Http\Request, and because that is
        // Arrayable it was emitted as an empty JSON response.
        expect(Util::isClassOrInterface('request'))->toBeFalse();
        expect(Util::isClassOrInterface('view'))->toBeFalse();
        expect(Util::isClassOrInterface('config'))->toBeFalse();
    });
});

describe('Type::string', function () {
    it('keeps ordinary literals as strings', function () {
        foreach (['view', 'cache', 'request', 'config', 'value', 'now', 'banana'] as $literal) {
            expect(Type::string($literal))
                ->toBeInstanceOf(StringType::class, "expected '{$literal}' to stay a string");
        }
    });

    it('still recognises real class names', function () {
        expect(Type::string(Request::class))->toBeInstanceOf(ClassType::class);
        expect(Type::string(Str::class))->toBeInstanceOf(ClassType::class);
    });
});
