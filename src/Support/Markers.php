<?php

namespace Laravel\Surveyor\Support;

use Laravel\Surveyor\Analyzed\IgnoreMarker;
use Laravel\Surveyor\Contracts\ConditionallyIgnored;
use Laravel\Surveyor\Contracts\Ignored;
use ReflectionClass;
use ReflectionParameter;
use Throwable;

class Markers
{
    /**
     * Extra attribute class names treated as ignore markers, for attributes
     * that cannot implement the Ignored contract, such as vendor attributes.
     *
     * @var list<string>
     */
    protected static array $attributes = [];

    /**
     * Doc block and comment tags treated as ignore markers, without the "@".
     *
     * @var list<string>
     */
    protected static array $tags = [];

    /** @var array<string, bool> */
    protected static array $resolved = [];

    /** @var array<string, bool> */
    protected static array $conditional = [];

    /** @var array<string, list<string>> */
    protected static array $constructorParameters = [];

    /** @var (callable(string|array): bool)|null */
    protected static $conditionResolver = null;

    /** @var array<string, bool> */
    protected static array $conditions = [];

    public static function registerAttributes(string ...$attributes): void
    {
        static::$attributes = array_values(array_unique([
            ...static::$attributes,
            ...array_map(fn (string $attribute) => ltrim($attribute, '\\'), $attributes),
        ]));

        static::$resolved = [];
        static::$conditional = [];
    }

    public static function registerTags(string ...$tags): void
    {
        static::$tags = array_values(array_unique([
            ...static::$tags,
            ...array_map(fn (string $tag) => ltrim($tag, '@'), $tags),
        ]));
    }

    /**
     * Hand over the resolving of marker conditions. Surveyor knows what a
     * condition is, not what a config key means.
     *
     * @param  (callable(string|array): bool)|null  $resolver
     */
    public static function registerConditionResolver(?callable $resolver): void
    {
        static::$conditionResolver = $resolver;
        static::$conditions = [];
    }

    /**
     * Whether anything is able to answer a condition at all. Without a resolver
     * a condition is unanswerable, which is not the same as failing.
     */
    public static function canResolveConditions(): bool
    {
        return static::$conditionResolver !== null;
    }

    public static function conditionPasses(string|array|null $condition): bool
    {
        if ($condition === null) {
            return false;
        }

        if (static::$conditionResolver === null) {
            return false;
        }

        return static::$conditions[serialize($condition)] ??= (bool) (static::$conditionResolver)($condition);
    }

    public static function isIgnoreAttribute(string $class): bool
    {
        $class = ltrim($class, '\\');

        return static::$resolved[$class] ??= static::resolveIgnoreAttribute($class);
    }

    /**
     * Whether a marker takes conditions at all. An attribute that is
     * unconditional by contract must not be turned off by an argument its class
     * would refuse: nothing is instantiated while reading a file, so the error
     * PHP would raise never happens.
     */
    public static function acceptsConditions(string $class): bool
    {
        $class = ltrim($class, '\\');

        return static::$conditional[$class] ??= class_exists($class)
            && is_subclass_of($class, ConditionallyIgnored::class);
    }

    /**
     * The name a positional argument carries, taken from the marker's own
     * constructor rather than assumed, so the reading of an attribute in a file
     * matches what instantiating it would produce.
     */
    public static function constructorParameterName(string $class, int $position): ?string
    {
        $class = ltrim($class, '\\');

        if (! isset(static::$constructorParameters[$class])) {
            try {
                $parameters = (new ReflectionClass($class))->getConstructor()?->getParameters() ?? [];
            } catch (Throwable) {
                $parameters = [];
            }

            static::$constructorParameters[$class] = array_map(
                fn (ReflectionParameter $parameter) => $parameter->getName(),
                $parameters,
            );
        }

        return static::$constructorParameters[$class][$position] ?? null;
    }

    public static function commentHasIgnoreTag(string $comment): bool
    {
        foreach (static::$tags as $tag) {
            if (preg_match('/@'.preg_quote($tag, '/').'\b/i', $comment) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find every comment in a file carrying an ignore tag, with the byte offset
     * it starts at. The parser hands a comment to the node that follows it,
     * which for an array key is the wrong key, and for the last key is no node
     * at all, so array items are matched on position instead.
     *
     * @return list<array{pos: int, line: int}>
     */
    public static function markerComments(string $code): array
    {
        if (static::$tags === []) {
            return [];
        }

        $tags = implode('|', array_map(fn (string $tag) => preg_quote($tag, '/'), static::$tags));

        if (! preg_match_all('/(?:\/\/|#|\/\*+|\*)[^\n]*?@(?:'.$tags.')\b/i', $code, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return array_map(fn (array $match) => [
            'pos' => $match[1],
            'line' => substr_count($code, "\n", 0, $match[1]) + 1,
        ], $matches[0]);
    }

    /**
     * Read the marker off a declaration through reflection, for members the
     * file being analyzed does not declare itself, such as one reached through
     * a trait or a parent class.
     *
     * @param  ReflectionClass<object>|\ReflectionClassConstant|\ReflectionEnum|\ReflectionEnumBackedCase|\ReflectionEnumUnitCase|\ReflectionMethod|\ReflectionProperty  $reflection
     */
    public static function fromReflection(object $reflection): ?IgnoreMarker
    {
        foreach ($reflection->getAttributes() as $attribute) {
            if (! static::isIgnoreAttribute($attribute->getName())) {
                continue;
            }

            try {
                $instance = $attribute->newInstance();
            } catch (Throwable) {
                // A marker that cannot be built still means the author wanted
                // this left out, so hide it either way.
                return new IgnoreMarker;
            }

            return $instance instanceof ConditionallyIgnored
                ? new IgnoreMarker($instance->unless(), $instance->when())
                : new IgnoreMarker;
        }

        $docBlock = $reflection->getDocComment();

        return $docBlock !== false && static::commentHasIgnoreTag($docBlock)
            ? new IgnoreMarker
            : null;
    }

    public static function reset(): void
    {
        static::$attributes = [];
        static::$tags = [];
        static::$resolved = [];
        static::$conditional = [];
        static::$constructorParameters = [];
        static::$conditionResolver = null;
        static::$conditions = [];
    }

    protected static function resolveIgnoreAttribute(string $class): bool
    {
        if (in_array($class, static::$attributes, true)) {
            return true;
        }

        return (class_exists($class) || interface_exists($class))
            && is_subclass_of($class, Ignored::class);
    }
}
