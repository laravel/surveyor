<?php

namespace Laravel\Surveyor\DocBlockResolvers\Type;

use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;
use PHPStan\PhpDocParser\Ast;

class IdentifierTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\IdentifierTypeNode $node)
    {
        $name = (string) $node->name;

        foreach ($this->scope->getTemplateTags() as $templateTag) {
            if ($templateTag->name === $name) {
                return $templateTag->bound;
            }
        }

        return $this->pseudoType($name) ?? Type::from($this->scope->getUse($name));
    }

    /**
     * Type::from() reads any name it doesn't know as a string literal, which
     * would turn PHPDoc pseudo-types into the literal of their own name.
     */
    protected function pseudoType(string $name): ?TypeContract
    {
        return match ($name) {
            'array-key' => Type::union(Type::int(), Type::string()),
            'positive-int',
            'negative-int',
            'non-positive-int',
            'non-negative-int',
            'non-zero-int' => Type::int(),
            'non-empty-string',
            'non-falsy-string',
            'truthy-string',
            'numeric-string',
            'literal-string',
            'non-empty-literal-string',
            'lowercase-string',
            'non-empty-lowercase-string',
            'uppercase-string',
            'non-empty-uppercase-string',
            'callable-string',
            'class-string',
            'interface-string',
            'trait-string',
            'enum-string' => Type::string(),
            'double' => Type::float(),
            'boolean' => Type::bool(),
            'number' => Type::number(),
            'numeric' => Type::union(Type::int(), Type::float(), Type::string()),
            'scalar' => Type::union(Type::int(), Type::float(), Type::string(), Type::bool()),
            'list', 'non-empty-list' => Type::arrayShape(Type::int(), Type::mixed()),
            'non-empty-array' => Type::from('array'),
            'never', 'never-return', 'never-returns', 'no-return' => Type::never(),
            // A class name can't contain a hyphen, so this is a pseudo-type we don't model.
            default => str_contains($name, '-') ? Type::mixed() : null,
        };
    }
}
