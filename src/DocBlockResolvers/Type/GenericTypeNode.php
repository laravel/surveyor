<?php

namespace Laravel\Surveyor\DocBlockResolvers\Type;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\IntersectionType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PHPStan\PhpDocParser\Ast;

class GenericTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\GenericTypeNode $node)
    {
        $genericTypes = collect($node->genericTypes)
            ->map(fn ($type) => $this->from($type))
            ->map(fn ($type) => $this->resolveGeneric($type))
            ->all();

        switch ($node->type->name) {
            case 'array':
            case 'non-empty-array':
                $baseType = array_shift($genericTypes);

                if ($baseType === null) {
                    // TODO: Circle back to this
                    $baseType = Type::mixed();
                }

                return Type::arrayShape($baseType, Type::union(...$genericTypes));
            case 'list':
                return Type::arrayShape(Type::int(), Type::union(...$genericTypes));
            case 'class-string':
                return Type::union(...array_map(fn ($t) => $this->resolveClassStringType($t), $genericTypes));
            case 'array-key':
                return Type::union(...$genericTypes);
            case 'object':
                return Type::union(...$genericTypes);
            case 'iterable':
                return $this->handleIterableType($node, $genericTypes);
            default:
                return $this->handleUnknownType($node);
        }
    }

    protected function resolveGeneric(?TypeContract $type)
    {
        if ($type === null) {
            return Type::mixed();
        }

        if (! $type instanceof StringType) {
            return $type;
        }

        foreach ($this->scope->getTemplateTags() as $templateTag) {
            if ($templateTag->name === $type->value) {
                return $templateTag->bound;
            }
        }

        return $type;
    }

    protected function resolveClassStringType(TypeContract $type)
    {
        if (Type::is($type, IntersectionType::class, UnionType::class)) {
            return Type::intersection(...array_map(fn ($t) => $this->resolveClassStringType($t), $type->types));
        }

        return new ClassType($this->scope->getUse($type->value));
    }

    protected function handleIterableType(Ast\Type\GenericTypeNode $node, array $genericTypes)
    {
        $tags = [];

        foreach ($node->genericTypes as $index => $tag) {
            if ($templateTag = $this->scope->getTemplateTag($tag->name)) {
                $tags[] = $templateTag;
            } else {
                $tags[] = $genericTypes[$index];
            }
        }

        return Type::arrayShape($tags[0] ?? Type::mixed(), $tags[1] ?? Type::mixed());
    }

    protected function handleUnknownType(Ast\Type\GenericTypeNode $node)
    {
        if ($node->type instanceof Ast\Type\IdentifierTypeNode) {
            $type = $this->from($node->type);

            if ($type instanceof ClassType) {
                return $type->setGenericTypes(array_map(fn ($t) => $this->from($t), $node->genericTypes));
            }

            if ($type instanceof ArrayShapeType) {
                // TODO: Return to this
                return $type;
            }

            return null;
        }

        Debug::ddAndOpen($node, 'unknown generic type');
    }
}
