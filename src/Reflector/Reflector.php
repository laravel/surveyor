<?php

namespace Laravel\StaticAnalyzer\Reflector;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\StaticAnalyzer\Parser\DocBlockParser;
use Laravel\StaticAnalyzer\Types\ClassType;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;
use ReflectionClass;

class Reflector
{
    public function __construct(
        protected DocBlockParser $docBlockParser,
    ) {
        //
    }

    public function methodReturnType(ClassType|string $class, string $method, ?Node $node = null): array
    {
        $reflection = $this->reflectClass($class);
        $returnTypes = [];

        if ($reflection->hasMethod($method)) {
            $methodReflection = $reflection->getMethod($method);

            if ($methodReflection->hasReturnType()) {
                $returnTypes[] = Type::from($methodReflection->getReturnType());
            }

            array_push(
                $returnTypes,
                ...$this->parseDocBlock($methodReflection->getDocComment(), $node)
            );
        }

        if ($reflection->getDocComment()) {
            array_push(
                $returnTypes,
                ...$this->parseDocBlock($reflection->getDocComment(), $node)
            );
        }

        if ($reflection->isSubclassOf(Model::class)) {
            array_push(
                $returnTypes,
                ...$this->methodReturnType(Builder::class, $method, $node),
            );
        }

        return $returnTypes;
    }

    protected function parseDocBlock(string $docBlock, ?Node $node = null): array
    {
        if (!$docBlock) {
            return [];
        }

        $result = $this->docBlockParser->parseReturn($docBlock, $node);

        if ($result) {
            return $result->toArray();
        }

        return [];
    }

    protected function reflectClass(ClassType|string $class): ReflectionClass
    {
        $className = $class instanceof ClassType ? $class->value : $class;

        return new ReflectionClass($className);
    }
}
