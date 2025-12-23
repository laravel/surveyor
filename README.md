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

### ClassResult

After analyzing a file containing a class, you'll receive a `ClassResult` object that provides access to the class's metadata:

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
```

### Properties

Access information about class properties:

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
```

### Constants

Access class constants:

```php
if ($classResult->hasConstant('STATUS_ACTIVE')) {
    $constant = $classResult->getConstant('STATUS_ACTIVE');
}
```

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

Thank you for considering contributing to Ranger! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

Please review [our security policy](https://github.com/laravel/ranger/security/policy) on how to report security vulnerabilities.

## License

Laravel Ranger is open-sourced software licensed under the [MIT license](LICENSE.md).
