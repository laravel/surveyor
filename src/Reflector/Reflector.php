<?php

namespace Laravel\StaticAnalyzer\Reflector;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\StaticAnalyzer\Parser\DocBlockParser;
use Laravel\StaticAnalyzer\Result\VariableTracker;
use Laravel\StaticAnalyzer\Types\ClassType;
use Laravel\StaticAnalyzer\Types\Contracts\Type as TypeContract;
use Laravel\StaticAnalyzer\Types\Type;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use ReflectionClass;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;

class Reflector
{
    public function __construct(
        protected DocBlockParser $docBlockParser,
    ) {
        //
    }

    public function functionReturnType(string $name, ?Node $node = null): array
    {
        $returnTypes = [];
        $reflection = new ReflectionFunction($name);

        $known = $this->tryKnownFunctions($name, $node);

        if ($known) {
            return $known;
        }

        if ($reflection->hasReturnType()) {
            $returnTypes[] = $this->returnType($reflection->getReturnType());
        }

        if ($reflection->getDocComment()) {
            $result = $this->docBlockParser->parseReturn($reflection->getDocComment(), $node);

            if ($result) {
                array_push($returnTypes, ...$result);
            }
        }

        return $returnTypes;
    }

    protected function tryKnownFunctions(string $name, ?CallLike $node = null): ?array
    {
        if ($name === 'compact') {
            $arr = collect($node->getArgs())->flatMap(function ($arg) use ($node) {
                if ($arg->value instanceof Node\Scalar\String_) {
                    return [
                        $arg->value->value => VariableTracker::current()->getAtLine($arg->value->value, $node->getStartLine())['type'],
                    ];
                }

                dd('not a string for compact', $arg);
            });

            return [Type::array($arr->all())];
        }

        return null;
    }

    public function methodReturnType(ClassType|string $class, string $method, ?Node $node = null): array
    {
        $reflection = $this->reflectClass($class);
        $returnTypes = [];

        if ($reflection->hasMethod($method)) {
            $methodReflection = $reflection->getMethod($method);

            if ($methodReflection->hasReturnType()) {
                $returnTypes[] = $this->returnType($methodReflection->getReturnType());
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

    public function returnType(ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType $returnType): ?TypeContract
    {
        if ($returnType instanceof ReflectionNamedType) {
            return Type::from($returnType->getName());
        }

        if ($returnType instanceof ReflectionUnionType) {
            return Type::union(
                ...collect($returnType->getTypes())
                    ->map(fn ($t) => Type::from($t->getName())->nullable($t->allowsNull())),
            );
        }

        if ($returnType instanceof ReflectionIntersectionType) {
            return Type::intersection(
                ...collect($returnType->getTypes())
                    ->map(fn ($t) => $this->returnType($t)?->nullable($t->allowsNull())),
            );
        }

        return null;
    }

    protected function parseDocBlock(string $docBlock, ?Node $node = null): array
    {
        if (! $docBlock) {
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
