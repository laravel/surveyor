<?php

namespace Laravel\Surveyor\DocBlockResolvers\Type;

use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\DocBlockResolvers\AbstractResolver;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Type;
use PHPStan\PhpDocParser\Ast;

class GenericTypeNode extends AbstractResolver
{
    public function resolve(Ast\Type\GenericTypeNode $node)
    {
        $genericTypes = collect($node->genericTypes)
            ->map(fn ($type) => $this->from($type))
            ->all();

        switch ($node->type->name) {
            case 'array':
                // TODO: Deal with template tags
                $baseType = array_shift($genericTypes);

                return Type::arrayShape($baseType, Type::union(...$genericTypes));
            case 'list':
                return Type::arrayShape(Type::int(), Type::union(...$genericTypes));
            case 'class-string':
                return Type::union(...$genericTypes);
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

    protected function handleIterableType(Ast\Type\GenericTypeNode $node, array $genericTypes)
    {
        $tags = [];

        foreach ($node->genericTypes as $index => $tag) {
            $templateTag = $this->scope->getTemplateTag($tag->name);

            if ($templateTag) {
                $tags[] = Type::templateTag($templateTag);
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
                return $type->setGenericTypes(array_map(fn ($type) => $type->name, $node->genericTypes));
            }

            if ($type instanceof ArrayShapeType) {
                // TODO: Return to this
                return $type;
            }

            Debug::ddAndOpen($type, $node, 'unknown type');
        }

        Debug::ddAndOpen($type, $node, 'unknown generic type');
    }
}
