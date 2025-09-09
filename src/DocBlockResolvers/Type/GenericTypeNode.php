<?php

namespace Laravel\StaticAnalyzer\DocBlockResolvers\Type;

use Laravel\StaticAnalyzer\Debug\Debug;
use Laravel\StaticAnalyzer\DocBlockResolvers\AbstractResolver;
use Laravel\StaticAnalyzer\Types\ArrayShapeType;
use Laravel\StaticAnalyzer\Types\ClassType;
use Laravel\StaticAnalyzer\Types\Type;
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
            default:
                return $this->handleUnknownType($node);
        }
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

            Debug::ddFromClass($type, $node, 'unknown type');
        }

        Debug::ddFromClass($type, $node, 'unknown generic type');
    }
}
