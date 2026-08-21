<p align="center">
<a href="https://github.com/laravel/surveyor/actions"><img src="https://github.com/laravel/surveyor/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/surveyor"><img src="https://img.shields.io/packagist/dt/laravel/surveyor" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/surveyor"><img src="https://img.shields.io/packagist/v/laravel/surveyor" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/surveyor"><img src="https://img.shields.io/packagist/l/laravel/surveyor" alt="License"></a>
</p>

# Laravel Surveyor

## Introduction

Laravel Surveyor is a powerful (mostly) static analysis tool designed to extract detailed PHP and Laravel-specific information from your code. It parses and analyzes PHP files to extract comprehensive metadata about classes, methods, properties, return types, and more — making this information available in a structured, consumable format for use by other tools and packages.

If you want high-level consumption of the results packaged in detailed DTOs, check out [Laravel Ranger](https://github.com/laravel/ranger).

> [!IMPORTANT]
> Surveyor is currently in Beta, the API is subject (and likely) to change prior to the v1.0.0 release. All notable changes will be documented in the [changelog](./CHANGELOG.md).

## Installation

You may install Surveyor via Composer:

```bash
composer require laravel/surveyor
```

## Notes

### Not Strictly Static Analysis

While Surveyor is _mostly_ static analysis, it does attempt to inspect your models (which means a brief database connection) and also inspects your app bindings to get more detailed information in the analysis.

### Performance

The performance is not where we want it to be yet, it runs slower than is ideal and uses more memory than we'd like. We're looking for active contributions in those specific areas.

## Basic Usage

### Analyzing a File

The primary way to use Surveyor is through the `Analyzer` class, which can analyze PHP files and extract detailed information:

```php
use Laravel\Surveyor\Analyzer\Analyzer;

$analyzer = app(Analyzer::class);

// Analyze a file by path
$result = $analyzer->analyze('/path/to/your/File.php');

// Access the analyzed scope
$scope = $result->analyzed();

// Access the class result
$classResult = $result->result();
```

### Analyzing a Class

You can also analyze a class directly by its fully qualified class name:

```php
use Laravel\Surveyor\Analyzer\Analyzer;

$analyzer = app(Analyzer::class);

$result = $analyzer->analyzeClass(\App\Models\User::class);
$classResult = $result->result();
```

## Working with Results

### ClassLikeResult

After analyzing a file containing a class or interface, you'll receive a `ClassLikeResult` object that provides access to its metadata:

```php
use Laravel\Surveyor\Analyzer\Analyzer;

$analyzer = app(Analyzer::class);
$classResult = $analyzer->analyzeClass(App\Models\User::class)->result();

// Get class information
$name = $classResult->name();           // 'App\Models\User'
$namespace = $classResult->namespace(); // 'App\Models'
$filePath = $classResult->filePath();

// Check inheritance
$extends = $classResult->extends();      // Returns array of parent classes
$implements = $classResult->implements(); // Returns array of interfaces

// Check if class implements specific interfaces
if ($classResult->implements(JsonSerializable::class)) {
    // ...
}
```

### Methods

Access information about class methods:

A method marked to be left out is hidden from every one of these, so `hasMethod()` returns false for it and `getMethod()` returns null. See [Ignore Markers](#ignore-markers).

```php
// Check if a method exists
if ($classResult->hasMethod('store')) {
    $method = $classResult->getMethod('store');

    // Get method name
    $methodName = $method->name();

    // Get return type
    $returnType = $method->returnType();

    // Get parameters
    $parameters = $method->parameters();

    // Get validation rules (if any are defined in the method)
    $rules = $method->validationRules();
}

// Get all public methods
$publicMethods = $classResult->publicMethods();

// Read a method past the marker filter, to inspect a marker rather than act on it
$raw = $classResult->method('store');
```

### Properties

Access information about class properties:

As with methods, a property marked to be left out is hidden from these, and `getProperty()` returns null for it.

```php
// Check if a property exists
if ($classResult->hasProperty('email')) {
    $property = $classResult->getProperty('email');

    $name = $property->name;
    $type = $property->type;
    $visibility = $property->visibility; // 'public', 'protected', or 'private'
}

// Get all public properties
$publicProperties = $classResult->publicProperties();

// Read a property past the marker filter
$raw = $classResult->property('email');
```

### Constants

Access class constants:

```php
if ($classResult->hasConstant('STATUS_ACTIVE')) {
    $constant = $classResult->getConstant('STATUS_ACTIVE');
}
```

## Ignore Markers

A tool that turns an application into client-side code needs a way for the author of that application to say "leave this one out". Surveyor recognizes the marker and hides what it covers; it does not decide what the marker is called, so each consumer ships an attribute in its own vocabulary.

An attribute class is a marker if it implements `Laravel\Surveyor\Contracts\Ignored`. Nothing needs registering:

```php
use Attribute;
use Laravel\Surveyor\Contracts\Ignored;

#[Attribute(Attribute::TARGET_ALL)]
final class Ignore implements Ignored
{
    //
}
```

Marked members are hidden wherever a result hands members out: `publicMethods()`, `publicProperties()`, `hasMethod()`, `getMethod()`, `hasProperty()`, `getProperty()`, `asArray()`, and `asJson()`. A marked class, interface, or method is reported by `isIgnored()`. To read a member past the filter, ask for it by name through `method()` or `property()`.

### Conditions

A marker can apply only some of the time. Implement `ConditionallyIgnored` and the two conditions are read from the attribute as written:

```php
use Laravel\Surveyor\Contracts\ConditionallyIgnored;

#[Attribute(Attribute::TARGET_ALL)]
final class Ignore implements ConditionallyIgnored
{
    public function __construct(
        public readonly string|array|null $unless = null,
        public readonly string|array|null $when = null,
    ) {
    }

    public function unless(): string|array|null
    {
        return $this->unless;
    }

    public function when(): string|array|null
    {
        return $this->when;
    }
}
```

`unless` keeps the declaration while its condition passes; `when` leaves it out while its condition passes. A condition is a config key or a `[class, method]` callable — nothing else counts as one, so a marker given anything else still hides. Surveyor does not decide what a condition means, so register a resolver:

```php
use Laravel\Surveyor\Support\Markers;

Markers::registerConditionResolver(fn (string|array $condition) => is_string($condition)
    ? (bool) config($condition, false)
    : (bool) app()->call($condition));
```

Register the resolver before reading results. Until one is registered nothing can answer a condition, and an unanswerable condition is not a failing one: every conditional marker hides, the same as one whose condition could not be read.

Conditions are resolved when a member is read, not while the file is analyzed, so a cached analysis holds the condition and never the answer to it. A condition that cannot be read at all — an expression surveyor cannot evaluate — leaves the declaration out whichever argument carried it.

Only a marker implementing `ConditionallyIgnored` carries conditions. An argument on any other marker is left alone, so an attribute that is unconditional by contract cannot be switched off by one. Name the constructor parameters `unless` and `when`. A positional argument is matched against the marker's own constructor parameters, but both readers then look for those two names: reading an attribute out of a file goes by the parameter name, while instantiating it goes through the contract methods. Parameters named anything else are not seen by the file reader, which leaves the marker unconditional there while instantiating it honors the condition.

### Comment Tags

An array key cannot carry an attribute, so register a comment tag instead:

```php
Markers::registerTags('ignore');
```

The tag can sit above the key or at the end of its line, and keys are matched by position rather than by what the parser attached the comment to:

```php
return [
    'name' => $this->name,
    'ssn' => $this->ssn, // @ignore
    // @ignore
    'token' => $this->token,
];
```

A tag in a doc block also marks the declaration it belongs to. No tag is honored until it is registered.

### Markers You Cannot Change

To treat an attribute from another package as a marker, register it by name:

```php
Markers::registerAttributes(\Vendor\Package\Attributes\Internal::class);
```

Markers on declarations reached through reflection rather than through the file — a member from a trait or a parent class — are read with `Markers::fromReflection($reflection)`, which returns an `IgnoreMarker` or null.

## Type System

Surveyor includes a comprehensive type system for representing PHP types. All types implement the `Laravel\Surveyor\Types\Contracts\Type` interface.

### Available Types

| Type               | Description                                     |
| ------------------ | ----------------------------------------------- |
| `StringType`       | Represents string values                        |
| `IntType`          | Represents integer values                       |
| `FloatType`        | Represents floating-point values                |
| `BoolType`         | Represents boolean values                       |
| `ArrayType`        | Represents array values                         |
| `ArrayShapeType`   | Represents arrays with specific key/value types |
| `ClassType`        | Represents class/object instances               |
| `UnionType`        | Represents union types (e.g., `string\|int`)    |
| `IntersectionType` | Represents intersection types                   |
| `NullType`         | Represents null values                          |
| `VoidType`         | Represents void return types                    |
| `MixedType`        | Represents mixed types                          |
| `CallableType`     | Represents callable types                       |
| `NeverType`        | Represents never return types                   |

### Creating Types

Use the `Type` factory class to create type instances:

```php
use Laravel\Surveyor\Types\Type;

// Primitive types
$stringType = Type::string();
$intType = Type::int();
$boolType = Type::bool();
$floatType = Type::float();
$nullType = Type::null();
$voidType = Type::void();
$mixedType = Type::mixed();

// Arrays
$arrayType = Type::array([]);
$arrayShapeType = Type::arrayShape(Type::string(), Type::int());

// Union types
$unionType = Type::union(Type::string(), Type::null());

// Intersection types
$intersectionType = Type::intersection($type1, $type2);

// Convert from values
$type = Type::from('string'); // Returns StringType
$type = Type::from(42);       // Returns IntType with value
```

### Type Checking

```php
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\ClassType;

// Check if type is a specific class
if (Type::is($returnType, StringType::class)) {
    // Handle string type
}

// Check multiple types
if (Type::is($returnType, StringType::class, ClassType::class)) {
    // Handle string or class type
}

// Compare types
if (Type::isSame($type1, $type2)) {
    // Types are the same
}
```

### Type Properties

All types support common properties:

```php
// Nullability
$type->isNullable();
$type->nullable(true);  // Mark as nullable

// Optionality
$type->isOptional();
$type->optional();      // Mark as optional
$type->required();      // Mark as required

// String representation
$typeString = $type->toString();
```

## Caching

Surveyor includes a caching system to improve performance when analyzing files repeatedly.

### Environment-Based Configuration

Configure caching via environment variables:

```env
SURVEYOR_CACHE_ENABLED=true
SURVEYOR_CACHE_DIR=/path/to/cache
```

### Programmatic Configuration

```php
use Laravel\Surveyor\Analyzer\AnalyzedCache;

// Enable disk caching
AnalyzedCache::setCacheDirectory(storage_path('surveyor-cache'));
AnalyzedCache::enable();

// Or use the convenience method
AnalyzedCache::enableDiskCache(storage_path('surveyor-cache'));

// Disable caching
AnalyzedCache::disable();

// Clear all cached data
AnalyzedCache::clear();

// Clear only in-memory cache
AnalyzedCache::clearMemory();
```

The cache automatically tracks file modification times and invalidates entries when files change. Dependencies between files are also tracked, so changes to parent classes or traits will invalidate dependent caches.

## Model Analysis

Surveyor includes special support for analyzing Eloquent models, including automatic detection of:

-   Database attributes and their types
-   Model relationships
-   Attribute accessors and mutators
-   Cast definitions

```php
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Analyzer\ModelAnalyzer;

$analyzer = app(Analyzer::class);
$result = $analyzer->analyzeClass(App\Models\User::class)->result();

// Properties will include database attributes
$emailProperty = $result->getProperty('email');

// Relationship methods are flagged
$method = $result->getMethod('posts');
if ($method->isModelRelation()) {
    // This is a relationship method
}
```

## Scope Information

When analyzing files, Surveyor provides detailed scope information including:

### Namespace and Use Statements

```php
$scope = $analyzer->analyze($path)->analyzed();

// Get namespace
$namespace = $scope->namespace();

// Get resolved use statement
$fullyQualified = $scope->getUse('Request'); // 'Illuminate\Http\Request'

// Get all use statements
$uses = $scope->uses();
```

### Variable State Tracking

Surveyor tracks variable types and states throughout method bodies:

```php
$stateTracker = $scope->state();

// Access tracked variables
$variables = $stateTracker->variables();

// Access tracked properties
$properties = $stateTracker->properties();
```

## Contributing

Thank you for considering contributing to Surveyor! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/surveyor/security/policy) on how to report security vulnerabilities.

## License

Laravel Surveyor is open-sourced software licensed under the [MIT license](LICENSE.md).
