<?php

use App\Attributes\Ignore;
use App\Attributes\Unrelated;
use App\Models\Note;
use App\PositionalAgreement;
use App\Support\MarkedByReflection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Surveyor\Analyzed\IgnoreMarker;
use Laravel\Surveyor\Analyzer\AnalyzedCache;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Support\Markers;

uses()->group('integration');

beforeEach(function () {
    AnalyzedCache::clear();
    Markers::reset();
    Markers::registerTags('wayfinder-ignore', 'ignore');
});

afterEach(function () {
    AnalyzedCache::clear();
    Markers::reset();
});

function marker(string $property): ?IgnoreMarker
{
    return Markers::fromReflection(
        new ReflectionProperty(MarkedByReflection::class, $property),
    );
}

/**
 * @return list<string>
 */
function attributeNames(string $model): array
{
    return array_map(
        fn ($property) => $property->name,
        app(Analyzer::class)->analyzeClass($model)->result()->publicProperties(),
    );
}

function analyzeFixture(string $code)
{
    $fixture = createPhpFixture($code);

    try {
        return app(Analyzer::class)->analyze($fixture)->result();
    } finally {
        unlink($fixture);
    }
}

describe('array item markers', function () {
    it('drops a key marked on the line above', function () {
        $result = analyzeFixture('
namespace App;

class LeadingMarker
{
    public function toArray(): array
    {
        return [
            "name" => "Taylor",
            // @ignore
            "ssn" => "secret",
            "email" => "taylor@laravel.com",
        ];
    }
}');

        expect(array_keys($result->getMethod('toArray')->returnType()->value))
            ->toBe(['name', 'email']);
    });

    it('drops the key a trailing marker sits on, not the one after it', function () {
        $result = analyzeFixture('
namespace App;

class TrailingMarker
{
    public function toArray(): array
    {
        return [
            "name" => "Taylor",
            "ssn" => "secret", // @ignore
            "email" => "taylor@laravel.com",
        ];
    }
}');

        expect(array_keys($result->getMethod('toArray')->returnType()->value))
            ->toBe(['name', 'email']);
    });

    it('drops the last key when the marker trails it', function () {
        $result = analyzeFixture('
namespace App;

class TrailingLastMarker
{
    public function toArray(): array
    {
        return [
            "name" => "Taylor",
            "ssn" => "secret", // @ignore
        ];
    }
}');

        expect(array_keys($result->getMethod('toArray')->returnType()->value))
            ->toBe(['name']);
    });

    it('drops the key a marker precedes on the same line', function () {
        $result = analyzeFixture('
namespace App;

class InlineMarker
{
    public function toArray(): array
    {
        return ["name" => "Taylor", /* @ignore */ "ssn" => "secret"];
    }
}');

        expect(array_keys($result->getMethod('toArray')->returnType()->value))
            ->toBe(['name']);
    });

    it('only drops the nested key a nested marker belongs to', function () {
        $result = analyzeFixture('
namespace App;

class NestedMarker
{
    public function toArray(): array
    {
        return [
            "user" => [
                "name" => "Taylor",
                "ssn" => "secret", // @ignore
            ],
            "email" => "taylor@laravel.com",
        ];
    }
}');

        $shape = $result->getMethod('toArray')->returnType()->value;

        expect(array_keys($shape))->toBe(['user', 'email']);
        expect(array_keys($shape['user']->value))->toBe(['name']);
    });

    it('drops a whole nested block when the marker trails it', function () {
        $result = analyzeFixture('
namespace App;

class NestedBlockMarker
{
    public function toArray(): array
    {
        return [
            "user" => [
                "name" => "Taylor",
            ], // @ignore
            "email" => "taylor@laravel.com",
        ];
    }
}');

        expect(array_keys($result->getMethod('toArray')->returnType()->value))
            ->toBe(['email']);
    });

    it('leaves keys alone when a marker sits outside the array', function () {
        $result = analyzeFixture('
namespace App;

class MarkerOutsideArray
{
    /** @ignore */
    public function toArray(): array
    {
        return [
            "name" => "Taylor",
            "email" => "taylor@laravel.com",
        ];
    }
}');

        // The doc block hides the method itself, so read past the filter to
        // check that it did not also take the first key of the array with it.
        expect($result->hasMethod('toArray'))->toBeFalse();
        expect(array_keys($result->method('toArray')->returnType()->value))
            ->toBe(['name', 'email']);
    });

    it('drops items from a list array', function () {
        $result = analyzeFixture('
namespace App;

class ListMarker
{
    public function toArray(): array
    {
        return [
            "one",
            // @ignore
            "two",
            "three",
        ];
    }
}');

        $values = array_map(fn ($type) => $type->value, $result->getMethod('toArray')->returnType()->value);

        expect($values)->toBe(['one', 'three']);
    });

    it('keeps marked keys when no tag is registered', function () {
        Markers::reset();

        $result = analyzeFixture('
namespace App;

class NoTagsRegistered
{
    public function toArray(): array
    {
        return [
            "name" => "Taylor",
            "ssn" => "secret", // @ignore
        ];
    }
}');

        expect(array_keys($result->getMethod('toArray')->returnType()->value))
            ->toBe(['name', 'ssn']);
    });
});

describe('attribute markers', function () {
    it('hides a property marked with an attribute implementing the contract', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;

class AttributeOnProperty
{
    public string $name = "Taylor";

    #[Ignore]
    public string $ssn = "secret";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('hides a property marked through an alias', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore as NeverShip;

class AliasedAttribute
{
    public string $name = "Taylor";

    #[NeverShip]
    public string $ssn = "secret";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('hides a property marked with a fully qualified attribute', function () {
        $result = analyzeFixture('
namespace App;

class FullyQualifiedAttribute
{
    public string $name = "Taylor";

    #[\App\Attributes\Ignore]
    public string $ssn = "secret";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('hides a promoted constructor property', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;

class PromotedProperty
{
    public function __construct(
        public string $name,
        #[Ignore]
        public string $ssn,
    ) {
    }
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('hides a method', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;

class AttributeOnMethod
{
    public function keep(): string
    {
        return "keep";
    }

    #[Ignore]
    public function secret(): string
    {
        return "secret";
    }
}');

        expect(array_values(array_map(fn ($method) => $method->name(), $result->publicMethods())))
            ->toBe(['keep']);
    });

    it('hides a member marked with a doc block tag', function () {
        $result = analyzeFixture('
namespace App;

class DocBlockTag
{
    public string $name = "Taylor";

    /** @ignore */
    public string $ssn = "secret";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('honors an attribute registered by name', function () {
        Markers::registerAttributes(Unrelated::class);

        $result = analyzeFixture('
namespace App;

use App\Attributes\Unrelated;

class RegisteredByName
{
    public string $name = "Taylor";

    #[Unrelated]
    public string $ssn = "secret";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('flags a class', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;

#[Ignore]
class IgnoredClass
{
    public string $name = "Taylor";
}');

        expect($result->isIgnored())->toBeTrue();
    });

    it('flags an interface', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;

#[Ignore]
interface IgnoredContract
{
    public function handle(): void;
}');

        expect($result->isIgnored())->toBeTrue();
    });

    it('flags an interface only while its condition fails', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.fake');

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

#[ConditionalIgnore(unless: "features.fake")]
interface ConditionalContract
{
    public function handle(): void;
}');

        expect($result->isIgnored())->toBeFalse();

        Markers::registerConditionResolver(fn ($condition) => false);

        expect($result->isIgnored())->toBeTrue();
    });

    it('leaves an unmarked class alone', function () {
        $result = analyzeFixture('
namespace App;

class PlainClass
{
    public string $name = "Taylor";
}');

        expect($result->isIgnored())->toBeFalse();
    });
});

describe('reading a member by name', function () {
    it('hides a marked method from every way of reading it', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;
use Illuminate\Contracts\Support\Arrayable;

class MarkedToArray implements Arrayable
{
    #[Ignore]
    public function toArray(): array
    {
        return ["ssn" => "000-00-0000"];
    }
}');

        expect($result->publicMethods())->toBe([]);
        expect($result->hasMethod('toArray'))->toBeFalse();
        expect($result->getMethod('toArray'))->toBeNull();
        expect($result->asArray())->toBeNull();
    });

    it('hides a marked jsonSerialize from asJson', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;
use JsonSerializable;

class MarkedJsonSerialize implements JsonSerializable
{
    #[Ignore]
    public function jsonSerialize(): array
    {
        return ["ssn" => "000-00-0000"];
    }
}');

        expect($result->asJson())->toBeNull();
    });

    it('hides a marked property from every way of reading it', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;

class MarkedProperty
{
    #[Ignore]
    public string $ssn = "000-00-0000";
}');

        expect($result->publicProperties())->toBe([]);
        expect($result->hasProperty('ssn'))->toBeFalse();
        expect($result->getProperty('ssn'))->toBeNull();
    });

    it('still reads a marked member through the unfiltered accessors', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;

class RawAccess
{
    #[Ignore]
    public string $ssn = "000-00-0000";

    #[Ignore]
    public function secret(): string
    {
        return "value";
    }
}');

        expect($result->property('ssn')?->name)->toBe('ssn');
        expect($result->method('secret')?->name())->toBe('secret');
    });

    it('keeps a conditionally marked member readable while its condition passes', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.fake');

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;
use Illuminate\Contracts\Support\Arrayable;

class ConditionalToArray implements Arrayable
{
    #[ConditionalIgnore(unless: "features.fake")]
    public function toArray(): array
    {
        return ["fake" => true];
    }
}');

        expect($result->hasMethod('toArray'))->toBeTrue();
        expect($result->asArray())->not->toBeNull();

        Markers::registerConditionResolver(fn ($condition) => false);

        expect($result->hasMethod('toArray'))->toBeFalse();
        expect($result->asArray())->toBeNull();
    });
});

describe('marker arguments', function () {
    it('does not read a condition off a marker that takes none', function () {
        $result = analyzeFixture('
namespace App;

use App\Attributes\Ignore;

class ArgumentOnPlainMarker
{
    #[Ignore(true)]
    public string $ssn = "000-00-0000";
}');

        expect($result->publicProperties())->toBe([]);
    });

    it('reads a positional condition from the marker own constructor order', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.retired');

        $result = analyzeFixture('
namespace App;

use App\Attributes\WhenFirstIgnore;

class WhenFirstPositional
{
    public string $name = "Taylor";

    // First position is when for this marker, so a passing condition hides it.
    #[WhenFirstIgnore("features.retired")]
    public string $retired = "value";

    #[WhenFirstIgnore("features.other")]
    public string $kept = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name', 'kept']);
    });

    it('agrees with what instantiating the marker would produce', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.retired');

        $fixture = createPhpFixture('
namespace App;

use App\Attributes\WhenFirstIgnore;

class PositionalAgreement
{
    #[WhenFirstIgnore("features.retired")]
    public string $retired = "value";
}');

        $result = app(Analyzer::class)->analyze($fixture)->result();

        require $fixture;

        $reflected = Markers::fromReflection(
            (new ReflectionClass(PositionalAgreement::class))->getProperty('retired'),
        );

        expect($result->property('retired')->ignoreMarker()->hides())->toBe($reflected->hides());
        expect($reflected->hides())->toBeTrue();

        unlink($fixture);
    });

    it('does not read a condition off an attribute registered by name', function () {
        Markers::registerAttributes(Unrelated::class);
        Markers::registerConditionResolver(fn ($condition) => true);

        $result = analyzeFixture('
namespace App;

use App\Attributes\Unrelated;

class RegisteredWithArgument
{
    #[Unrelated("features.fake")]
    public string $ssn = "000-00-0000";
}');

        expect($result->publicProperties())->toBe([]);
    });
});

describe('conditional markers', function () {
    it('keeps a member when the condition passes', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.fake');

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class ConditionPasses
{
    public string $name = "Taylor";

    #[ConditionalIgnore(unless: "features.fake")]
    public string $fake = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name', 'fake']);
    });

    it('hides a member when the condition fails', function () {
        Markers::registerConditionResolver(fn ($condition) => false);

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class ConditionFails
{
    public string $name = "Taylor";

    #[ConditionalIgnore(unless: "features.fake")]
    public string $fake = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('reads the condition from a positional argument', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.fake');

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class PositionalCondition
{
    #[ConditionalIgnore("features.fake")]
    public string $fake = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['fake']);
    });

    it('reads a callable condition', function () {
        Markers::registerConditionResolver(
            fn ($condition) => $condition === [Ignore::class, 'enabled'],
        );

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;
use App\Attributes\Ignore;

class CallableCondition
{
    #[ConditionalIgnore(unless: [Ignore::class, "enabled"])]
    public string $fake = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['fake']);
    });

    it('does not treat a bool as a condition, so the marker still hides', function () {
        Markers::registerConditionResolver(fn ($condition) => true);

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class BoolCondition
{
    #[ConditionalIgnore(unless: true)]
    public string $named = "value";

    #[ConditionalIgnore(true)]
    public string $positional = "value";

    #[ConditionalIgnore(when: false)]
    public string $when = "value";
}');

        expect($result->publicProperties())->toBe([]);
    });

    it('hides both kinds of condition when nothing can answer them', function () {
        Markers::reset();
        Markers::registerTags('ignore');

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class NoResolverRegistered
{
    public string $name = "Taylor";

    #[ConditionalIgnore(unless: "features.fake")]
    public string $fake = "value";

    #[ConditionalIgnore(when: "features.retired")]
    public string $retired = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('does not treat a malformed callable as a condition', function () {
        Markers::registerConditionResolver(fn ($condition) => true);

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class MalformedCallable
{
    #[ConditionalIgnore(unless: ["only-one-element"])]
    public string $ssn = "value";
}');

        expect($result->publicProperties())->toBe([]);
    });

    it('hides a member when a when condition passes', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.retired');

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class WhenPasses
{
    public string $name = "Taylor";

    #[ConditionalIgnore(when: "features.retired")]
    public string $retired = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name']);
    });

    it('keeps a member when a when condition fails', function () {
        Markers::registerConditionResolver(fn ($condition) => false);

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class WhenFails
{
    public string $name = "Taylor";

    #[ConditionalIgnore(when: "features.retired")]
    public string $retired = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['name', 'retired']);
    });

    it('hides a member when either condition asks for it', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.retired');

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class BothConditions
{
    // Kept by unless, hidden by when: hiding wins.
    #[ConditionalIgnore(unless: "features.retired", when: "features.retired")]
    public string $retired = "value";

    #[ConditionalIgnore(unless: "features.retired", when: "features.other")]
    public string $kept = "value";
}');

        expect(array_map(fn ($property) => $property->name, $result->publicProperties()))
            ->toBe(['kept']);
    });

    it('hides a member when a when condition cannot be read', function () {
        Markers::registerConditionResolver(fn ($condition) => false);

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class UnreadableWhenCondition
{
    #[ConditionalIgnore(when: SOME_UNDEFINED_CONSTANT)]
    public string $retired = "value";
}');

        expect($result->publicProperties())->toBe([]);
    });

    it('hides a member when the condition cannot be read', function () {
        Markers::registerConditionResolver(fn ($condition) => true);

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class UnreadableCondition
{
    #[ConditionalIgnore(unless: SOME_UNDEFINED_CONSTANT)]
    public string $fake = "value";
}');

        expect($result->publicProperties())->toBe([]);
    });

    it('resolves the condition each time rather than baking it into the analysis', function () {
        Markers::registerConditionResolver(fn ($condition) => true);

        $result = analyzeFixture('
namespace App;

use App\Attributes\ConditionalIgnore;

class ResolvedLate
{
    #[ConditionalIgnore(unless: "features.fake")]
    public string $fake = "value";
}');

        expect($result->publicProperties())->toHaveCount(1);

        Markers::registerConditionResolver(fn ($condition) => false);

        expect($result->publicProperties())->toBe([]);
    });
});

describe('reading a marker through reflection', function () {
    it('reads a marker off a property', function () {
        expect(marker('marked')?->hides())->toBeTrue();
    });

    it('reads a marker off a method', function () {
        $method = new ReflectionMethod(MarkedByReflection::class, 'markedMethod');

        expect(Markers::fromReflection($method)?->hides())->toBeTrue();
    });

    it('reads a marker off a class', function () {
        $class = new ReflectionClass(Ignore::class);

        expect(Markers::fromReflection($class))->toBeNull();
    });

    it('reads nothing off an unmarked property', function () {
        expect(marker('plain'))->toBeNull();
    });

    it('reads nothing off an unrelated attribute', function () {
        expect(marker('unrelated'))->toBeNull();
    });

    it('reads a doc block tag', function () {
        expect(marker('taggedInDocBlock')?->hides())->toBeTrue();
    });

    it('hides when a marker cannot be built', function () {
        // #[Ignore(true)] on a marker with no constructor: instantiating throws,
        // which must not be mistaken for a marker that was switched off.
        expect(marker('argumentOnPlainMarker')?->hides())->toBeTrue();
    });

    it('carries the conditions off a conditional marker', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.fake');

        expect(marker('keptWhileFake')?->unless)->toBe('features.fake');
        expect(marker('keptWhileFake')?->hides())->toBeFalse();
        expect(marker('hiddenWhileRetired')?->when)->toBe('features.retired');
        expect(marker('hiddenWhileRetired')?->hides())->toBeFalse();

        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.retired');

        expect(marker('keptWhileFake')?->hides())->toBeTrue();
        expect(marker('hiddenWhileRetired')?->hides())->toBeTrue();
    });
});

describe('markers on inherited members', function () {
    beforeEach(function () {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
    });

    afterEach(function () {
        Schema::dropIfExists('notes');
    });

    it('hides an accessor marked in a trait or a parent class', function () {
        $names = attributeNames(Note::class);

        expect($names)->toContain('title');
        expect($names)->toContain('trait_public');
        expect($names)->toContain('parent_public');
        expect($names)->not->toContain('trait_secret');
        expect($names)->not->toContain('parent_secret');
    });

    it('carries the condition off an accessor marked in a trait', function () {
        Markers::registerConditionResolver(fn ($condition) => $condition === 'features.notes');

        expect(attributeNames(Note::class))->toContain('trait_conditional');

        AnalyzedCache::clear();
        Markers::registerConditionResolver(fn ($condition) => false);

        expect(attributeNames(Note::class))->not->toContain('trait_conditional');
    });
});
