<?php

namespace Laravel\Surveyor\Reflector;

use DateInterval;
use DatePeriod;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Concerns\LazilyLoadsDependencies;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\Parser;
use Laravel\Surveyor\Support\Util;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Type;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionClass;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Throwable;

class Reflector
{
    use LazilyLoadsDependencies;

    protected Scope $scope;

    protected array $appBindings;

    protected array $reflectedClasses = [];

    public function setScope(Scope $scope)
    {
        $this->scope = $scope;
        $this->getDocBlockParser()->setScope($scope);
    }

    public function functionReturnType(string $name, ?Node $node = null): array
    {
        if (! function_exists($name)) {
            return [Type::mixed()];
        }

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
            $this->getDocBlockParser()->parseTemplateTags($reflection->getDocComment());

            array_push(
                $returnTypes,
                ...$this->getDocBlockParser()->parseReturn($reflection->getDocComment(), $node),
            );
        }

        return $returnTypes;
    }

    protected function tryKnownFunctions(string $name, ?CallLike $node = null): ?array
    {
        if ($name === 'compact') {
            $arr = collect($node->getArgs())->flatMap(function ($arg) {
                if ($arg->value instanceof Node\Scalar\String_) {
                    $arg->name = new Node\Identifier($arg->value->value);

                    return [
                        $arg->value->value => $this->scope->state()->getAtLine($arg)->type(),
                    ];
                }

                return null;
            })->filter()->values();

            return [Type::array($arr->all())];
        }

        if ($name === 'app') {
            if (count($node->getArgs()) === 0) {
                return [new ClassType(Application::class)];
            }

            $firstArg = $node->getArgs()[0];

            if ($firstArg->value instanceof Node\Scalar\String_) {
                if ($this->getAppBinding($firstArg->value->value)) {
                    return $this->getAppBinding($firstArg->value->value)->getConcrete();
                }
            }

            return [
                $this->getNodeResolver()->from(
                    $firstArg->value,
                    $this->scope,
                ),
            ];
        }

        if ($name === 'get_class_vars') {
            $result = $this->getNodeResolver()->from(
                $node->getArgs()[0]->value,
                $this->scope,
            );

            if (! Type::is($result, ClassType::class)) {
                return null;
            }

            $reflection = $this->reflectClass($result->value);

            $reflectedProperties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

            $properties = [];

            foreach ($reflectedProperties as $property) {
                $properties[$property->getName()] = $this->propertyType($property->getName(), $result->value);
            }

            return [Type::array($properties)];
        }

        return null;
    }

    public function propertyType(string $name, ClassType|string $class, ?Node $node = null): ?TypeContract
    {
        $reflection = $this->reflectClass($class);

        if ($reflection->getName() === DateInterval::class) {
            return match ($name) {
                'd' => Type::int(),
                'days' => Type::int(),
                'f' => Type::float(),
                'h' => Type::int(),
                'i' => Type::int(),
                'invert' => Type::int(),
                'm' => Type::int(),
                's' => Type::int(),
                'y' => Type::int(),
            };
        }

        if ($reflection->getName() === DatePeriod::class) {
            return match ($name) {
                'current' => Type::from(DateTimeInterface::class),
                'end' => Type::from(DateTimeInterface::class),
                'include_start_date' => Type::bool(),
                'interval' => Type::from(DateInterval::class),
                'recurrences' => Type::int(),
                'start' => Type::from(DateTimeInterface::class),
            };
        }

        if ($reflection->hasProperty($name)) {
            $propertyReflection = $reflection->getProperty($name);

            if (
                $propertyReflection->getDocComment()
                && $result = $this->getDocBlockParser()->parseVar($propertyReflection->getDocComment())
            ) {
                return $result;
            }

            if ($propertyReflection->hasType()) {
                return $this->returnType($propertyReflection->getType());
            }

            if ($propertyReflection->isStatic() && $propertyReflection->hasDefaultValue()) {
                return Type::from($propertyReflection->getValue());
            }
        }

        $reflections = [$reflection, ...$reflection->getTraits()];
        $current = $reflection;

        while ($current->getParentClass()) {
            $reflections[] = $current->getParentClass();
            $current = $current->getParentClass();
        }

        foreach ($reflections as $ref) {
            if ($ref->getDocComment()) {
                $result = $this->getDocBlockParser()->parseProperties($ref->getDocComment());

                if (array_key_exists($name, $result)) {
                    return $result[$name];
                }
            }
        }

        if ($reflection->isSubclassOf(Model::class) && $reflection->hasMethod($name)) {
            return Type::union(...$this->methodReturnType($class, $name));
        }

        if ($reflection->getName() === 'BackedEnum' && $name === 'value') {
            return Type::union(Type::string(), Type::int());
        }

        // Debug::ddAndOpen($node, $reflection, $reflections, Debug::trace(), $this->scope->state()->variables(), $name, $class, 'property doesnt exist');

        return null;
    }

    public function constantType(string $constant, ClassType|string $class, ?Node $node = null): ?TypeContract
    {
        $reflection = $this->reflectClass($class);

        if (! $reflection->hasConstant($constant)) {
            return null;
        }

        $constantValue = $reflection->getConstant($constant);

        if ($reflection->isEnum()) {
            return Type::from($constantValue->value);
        }

        return Type::from($constantValue);
    }

    public function methodReturnType(ClassType|string $class, string $method, ?Node $node = null): array
    {
        $className = $class instanceof ClassType ? $class->value : $class;
        $reflection = $this->reflectClass($class);

        if ($this->scope->entityName() !== $reflection->getName()) {
            $analyzed = $this->getAnalyzer()->analyze($reflection->getFileName());
            $scope = $analyzed->analyzed();

            if ($scope) {
                $this->setScope($scope);
            }
        }

        $returnTypes = [];

        if ($reflection->hasMethod($method)) {
            $methodReflection = $reflection->getMethod($method);

            if ($methodReflection->hasReturnType()) {
                $returnTypes[] = $this->returnType($methodReflection->getReturnType());
            }

            if ($methodReflection->getDocComment()) {
                array_push(
                    $returnTypes,
                    ...$this->parseDocBlock($methodReflection->getDocComment()),
                );
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

        if (count($returnTypes) === 0 && $reflection->isSubclassOf(Model::class)) {
            array_push(
                $returnTypes,
                ...$this->methodReturnType(Builder::class, $method, $node),
            );
        }

        if (count($returnTypes) > 0) {
            return $returnTypes;
        }

        if (! $node || ! $reflection->isInstantiable() || ! $this->hasMacro($className, $node)) {
            return [Type::mixed()];
        }

        $reflectionProperty = $reflection->getProperty('macros');
        $reflectionProperty->setAccessible(true);
        $macros = $reflectionProperty->getValue($reflection);

        $funcReflection = new ReflectionFunction($macros[$node->name->name]);
        $parser = $this->getParser();

        // TODO: We're parsing twice here, fix this
        $parsed = $parser->parse($funcReflection, $funcReflection->getFilename());

        $analyzed = $this->getAnalyzer()->analyze($funcReflection->getFilename());

        $funcNode = $parser->nodeFinder()->findFirst(
            $parsed,
            fn ($n) => ($n instanceof Node\Expr\Closure || $n instanceof Node\Expr\ArrowFunction)
                && $n->getStartLine() === $funcReflection->getStartLine(),
        );

        $methodNodes = $parser->nodeFinder()->find(
            $parsed,
            fn ($n) => $n instanceof ClassMethod && $n->getStartLine() < $funcReflection->getStartLine(),
        );

        $methodName = end($methodNodes)->name->name;

        $result = $this->getNodeResolver()->from(
            $funcNode,
            $analyzed->scope()->methodScope($methodName),
        );

        if ($result) {
            return [$result];
        }

        return [Type::mixed()];
    }

    public function returnType(ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType $returnType): ?TypeContract
    {
        if ($returnType instanceof ReflectionNamedType) {
            if (in_array($returnType->getName(), ['static', 'self'])) {
                return Type::from($this->scope->entityName());
            }

            return Type::from($returnType->getName())->nullable($returnType->allowsNull());
        }

        if ($returnType instanceof ReflectionUnionType) {
            return Type::union(
                ...array_map(
                    fn ($t) => Type::from($t->getName())->nullable($t->allowsNull()),
                    $returnType->getTypes(),
                ),
            );
        }

        if ($returnType instanceof ReflectionIntersectionType) {
            return Type::intersection(
                ...array_map(
                    fn ($t) => $this->returnType($t)?->nullable($t->allowsNull()),
                    $returnType->getTypes(),
                ),
            );
        }

        return null;
    }

    public function paramType(Node\Param $node, string $className, string $methodName): ?TypeContract
    {
        // TODO: This is really just analyzing the doc block... do both?
        $reflection = $this->reflectClass($className);
        $methodReflection = $reflection->getMethod($methodName);

        if ($docBlock = $methodReflection->getDocComment()) {
            $result = $this->getDocBlockParser()->parseParam($docBlock, $node->var->name);

            if ($result) {
                return $result;
            }
        }

        return null;
    }

    protected function parseDocBlock(string $docBlock, ?Node $node = null): array
    {
        if (! $docBlock) {
            return [];
        }

        return $this->getDocBlockParser()->parseReturn($docBlock, $node);
    }

    public function reflectClass(ClassType|string $class): ReflectionClass
    {
        $className = $class instanceof ClassType ? $class->value : $class;

        if (isset($this->reflectedClasses[$className])) {
            return $this->reflectedClasses[$className];
        }

        if (! Util::isClassOrInterface($className)) {
            $className = $this->scope->getUse($className);
        }

        if (! Util::isClassOrInterface($className) && str_contains($className, '\\')) {
            // Try again from the base of the name, weird bug in the parser
            $parts = explode('\\', $className);
            $end = array_pop($parts);
            $className = $this->scope->getUse($end);
        }

        if (! Util::isClassOrInterface($className)) {
            Debug::ddAndOpen($className, Debug::trace(), 'class does not exist');
        }

        $this->reflectedClasses[$className] = new ReflectionClass($className);

        return new ReflectionClass($className);
    }

    protected function hasMacro(string $className, Node $node): bool
    {
        try {
            return method_exists($className, 'hasMacro') && $className::hasMacro($node->name->name);
        } catch (Throwable $e) {
            return false;
        }
    }

    protected function getAppBinding($key)
    {
        $this->appBindings ??= app()->getBindings();

        return $this->appBindings[$key] ?? null;
    }
}
