<?php

namespace Laravel\Surveyor\NodeResolvers\Shared;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\MixedType;
use PhpParser\Node;

/**
 * Builds the type for an Eloquent attribute accessor, capturing the getter's
 * return type as a generic so the model analyzer can read it back.
 *
 * Requires ResolvesClosureReturnTypes on the consuming class.
 */
trait ResolvesEloquentAttributes
{
    /**
     * The ways an Attribute can be built where the getter is the first
     * positional argument. Attribute::set() is left out on purpose, since its
     * only argument is the setter.
     *
     * @var list<string>
     */
    protected array $attributeFactoryMethods = ['make', 'get'];

    protected function isAttributeFactory(mixed $class, string $method): bool
    {
        return $class instanceof ClassType
            && $class->resolved() === Attribute::class
            && in_array($method, $this->attributeFactoryMethods, true);
    }

    /**
     * @param  array<int, Node\Arg>  $args
     */
    protected function attributeTypeFrom(array $args): ClassType
    {
        $attributeType = new ClassType(Attribute::class);

        if ($getArg = $this->findGetArgument($args)) {
            $getType = $this->resolveClosureReturnType($getArg->value);

            // A getter we could not read tells us nothing. Leaving the generic
            // off lets the model analyzer fall back to the PHPDoc, which is
            // usually more specific than mixed.
            if ($getType !== null && ! $getType instanceof MixedType) {
                $attributeType->setGenericTypes([$getType]);
            }
        }

        return $attributeType;
    }

    /**
     * @param  array<int, Node\Arg>  $args
     */
    protected function findGetArgument(array $args): ?Node\Arg
    {
        foreach ($args as $arg) {
            if ($arg->name?->name === 'get') {
                return $arg;
            }
        }

        // A named argument anywhere means the first positional slot is not the
        // getter, so only fall back when nothing is named.
        foreach ($args as $arg) {
            if ($arg->name !== null) {
                return null;
            }
        }

        return $args[0] ?? null;
    }
}
