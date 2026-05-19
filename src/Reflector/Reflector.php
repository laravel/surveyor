<?php

namespace Laravel\Surveyor\Reflector;

use DateInterval;
use DatePeriod;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Concerns\LazilyLoadsDependencies;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Support\Util;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\TemplateTagType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;
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

    protected array $cachedClasses = [];

    protected array $cachedFunctions = [];

    protected array $cachedMacros = [];

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
        $reflection = $this->reflectFunction($name);

        if ($known = $this->tryKnownFunctions($name, $node)) {
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

    protected function reflectFunction(string $name): ReflectionFunction
    {
        return $this->cachedFunctions[$name] ??= new ReflectionFunction($name);
    }

    protected function tryKnownFunctions(string $name, ?CallLike $node = null): ?array
    {
        return match ($name) {
            'array_merge' => $this->handleFunctionArrayMerge($node),
            'compact' => $this->handleFunctionCompact($node),
            'app' => $this->handleFunctionApp($node),
            'get_class_vars' => $this->handleFunctionGetClassVars($node),
            'collect' => $this->handleFunctionCollect($node),
            default => null,
        };
    }

    protected function handleFunctionArrayMerge(?CallLike $node): ?array
    {
        $args = collect($node->getArgs())
            ->map(fn ($arg) => $this->getNodeResolver()->from($arg->value, $this->scope))
            ->filter(fn ($arg) => Type::is($arg, ArrayType::class, UnionType::class));

        if ($args->every(fn ($arg) => Type::is($arg, ArrayType::class))) {
            return [
                Type::array($args->flatMap(fn ($arg) => $arg->value)->all()),
            ];
        }

        $possibilities = $args->map(function ($arg) {
            if (Type::is($arg, UnionType::class)) {
                return collect($arg->types)->filter(fn ($type) => Type::is($type, ArrayType::class))->all();
            }

            return [$arg];
        });

        $firstSet = $possibilities->shift();
        $cartesian = collect($firstSet)->crossJoin(...$possibilities);

        $results = $cartesian->map(fn ($combination) => Type::array(
            collect($combination)->flatMap(fn ($arrType) => $arrType->value)->all()
        ));

        return [Type::union(...$results)];
    }

    protected function handleFunctionCompact(?CallLike $node): ?array
    {
        $arr = collect($node->getArgs())->flatMap(function ($arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $arg->name = new Node\Identifier($arg->value->value);

                return [
                    $arg->value->value => $this->scope->state()->getAtLine($arg)->type(),
                ];
            }

            return null;
        })->filter();

        return [Type::array($arr->all())];
    }

    protected function handleFunctionApp(?CallLike $node): ?array
    {
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

    protected function handleFunctionGetClassVars(?CallLike $node): ?array
    {
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

    protected function handleFunctionCollect(?CallLike $node): ?array
    {
        $collectionClass = Collection::class;

        if (! $node || count($node->getArgs()) === 0) {
            return [new ClassType($collectionClass)];
        }

        $argType = $this->getNodeResolver()->from($node->getArgs()[0]->value, $this->scope);

        if ($argType instanceof ClassType) {
            return [$argType];
        }

        if ($argType instanceof ArrayType) {
            $keyType = $argType->isList() ? Type::int() : $argType->keyType();
            $valueType = $this->generalizeLiteralType($argType->valueType());

            return [(new ClassType($collectionClass))->setGenericTypes([$keyType, $valueType])];
        }

        if ($argType instanceof ArrayShapeType) {
            return [(new ClassType($collectionClass))->setGenericTypes([$argType->keyType, $argType->valueType])];
        }

        return [new ClassType($collectionClass)];
    }

    /**
     * Generalize a literal type to its base type (e.g., StringType('a') → StringType()).
     * This is needed when building Collection generic types from literal array values.
     */
    protected function generalizeLiteralType(TypeContract $type): TypeContract
    {
        if ($type instanceof StringType && $type->value !== null) {
            return new StringType;
        }

        if ($type instanceof IntType && $type->value !== null) {
            return new IntType;
        }

        if ($type instanceof FloatType && $type->value !== null) {
            return new FloatType;
        }

        if ($type instanceof UnionType) {
            return Type::union(...array_map(fn ($t) => $this->generalizeLiteralType($t), $type->types));
        }

        return $type;
    }

    /**
     * When a method is declared in a parent class, resolve the @extends chain to map
     * the parent class's template names to the child class's resolved generic types.
     * E.g., EloquentCollection extends Collection<TKey, TModel>, so Collection's TValue
     * maps to EloquentCollection's TModel binding.
     */
    protected function resolveExtendsChain(ReflectionClass $childReflection, string $parentClassName): void
    {
        $extendsTypes = $this->getDocBlockParser()->parseExtendsTags($childReflection->getDocComment());

        foreach ($extendsTypes as $extendsType) {
            if (! $extendsType instanceof ClassType) {
                continue;
            }

            // Match the @extends class name (handle leading backslash)
            $extendsClassName = ltrim($extendsType->value, '\\');
            if ($extendsClassName !== $parentClassName) {
                continue;
            }

            try {
                $parentReflection = $this->reflectClass($parentClassName);
            } catch (Throwable $e) {
                continue;
            }

            if (! $parentReflection->getDocComment()) {
                continue;
            }

            $parentTemplateNames = $this->getDocBlockParser()->getAllTemplateTagNames($parentReflection->getDocComment());
            $extendsGenericTypes = $extendsType->genericTypes();

            $additionalTags = [];
            foreach ($parentTemplateNames as $i => $parentName) {
                if (isset($extendsGenericTypes[$i])) {
                    $additionalTags[] = new TemplateTagType($parentName, $extendsGenericTypes[$i], null, null, null);
                }
            }

            if (count($additionalTags) > 0) {
                $currentTags = $this->scope->getTemplateTags();
                $existingNames = array_map(fn ($t) => $t->name, $currentTags);
                $newTags = array_values(array_filter($additionalTags, fn ($t) => ! in_array($t->name, $existingNames)));
                $this->scope->setTemplateTags(array_merge($currentTags, $newTags));
            }

            break;
        }
    }

    /**
     * Bind method-level @template tags (like TFirstDefault) that are not already
     * in the scope. Unbound method templates default to NullType, which correctly
     * models optional parameters that default to null.
     */
    protected function bindMethodLevelTemplateTags(string $methodDocBlock): void
    {
        $methodTemplateNames = $this->getDocBlockParser()->getTemplateTagNames($methodDocBlock);

        if (count($methodTemplateNames) === 0) {
            return;
        }

        $currentTags = $this->scope->getTemplateTags();
        $existingNames = array_map(fn ($t) => $t->name, $currentTags);

        $newTags = [];
        foreach ($methodTemplateNames as $name) {
            if (! in_array($name, $existingNames)) {
                $newTags[] = new TemplateTagType($name, Type::null(), null, null, null);
            }
        }

        if (count($newTags) > 0) {
            $this->scope->setTemplateTags(array_merge($currentTags, $newTags));
        }
    }

    /**
     * Bind method-level templates that appear as the return type of a callable @param.
     * e.g. @param callable(TValue, TKey): TMapValue $callback — binds TMapValue to the
     * actual return type of the closure passed at the corresponding argument position.
     *
     * @param array<int, \Laravel\Surveyor\Types\Contracts\Type> $closureReturnTypes
     */
    protected function bindCallableArgTemplates(string $methodDocBlock, \ReflectionMethod $methodReflection, array $closureReturnTypes): void
    {
        $callableReturnTemplates = $this->getDocBlockParser()->getCallableParamReturnTemplates($methodDocBlock);

        if (empty($callableReturnTemplates)) {
            return;
        }

        $paramNames = array_map(
            fn ($p) => $p->getName(),
            $methodReflection->getParameters(),
        );

        $currentTags = $this->scope->getTemplateTags();
        $updated = false;

        foreach ($callableReturnTemplates as $paramName => $templateName) {
            $position = array_search($paramName, $paramNames, true);

            if ($position === false || ! isset($closureReturnTypes[$position])) {
                continue;
            }

            $closureReturnType = $closureReturnTypes[$position];

            foreach ($currentTags as $i => $tag) {
                if ($tag->name === $templateName) {
                    $currentTags[$i] = new TemplateTagType($tag->name, $closureReturnType, $tag->default, $tag->lowerBound, $tag->description);
                    $updated = true;
                    break;
                }
            }
        }

        if ($updated) {
            $this->scope->setTemplateTags($currentTags);
        }
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
                    return $result[$name]['type'];
                }
            }
        }

        if ($reflection->isSubclassOf(Model::class) && $reflection->hasMethod($name)) {
            return Type::union(...$this->methodReturnType($class, $name));
        }

        if ($reflection->getName() === 'BackedEnum' && $name === 'value') {
            return Type::union(Type::string(), Type::int());
        }

        if ($this->scope->entityName() !== $reflection->getName()) {
            $analyzed = $this->getAnalyzer()->analyze($reflection->getFileName());
            $scope = $analyzed->analyzed();

            // If we've gotten this far, check the scope and see if we've already figured it out
            return $scope->state()->properties()->get($name);
        }

        // If we've gotten this far, check the scope and see if we've already figured it out
        if ($result = $this->scope->state()->properties()->get($name)) {
            return $result;
        }

        return null;
    }

    public function constantType(string $constant, ClassType|string $class, ?Node $node = null): ?TypeContract
    {
        try {
            $reflection = $this->reflectClass($class);
        } catch (Throwable $e) {
            Debug::error($e, 'Error reflecting class');

            return null;
        }

        if (! $reflection->hasConstant($constant)) {
            return null;
        }

        $constantValue = $reflection->getConstant($constant);

        if ($reflection->isEnum()) {
            return Type::from($constantValue->value);
        }

        return Type::from($constantValue);
    }

    public function methodReturnType(ClassType|string $class, string $method, ?Node $node = null, array $closureReturnTypes = []): array
    {
        $className = $class instanceof ClassType ? $class->value : $class;
        $reflection = $this->reflectClass($class);

        if ($this->scope->entityName() !== $reflection->getName()) {
            $analyzed = $this->getAnalyzer()->analyze($reflection->getFileName());

            if ($scope = $analyzed->analyzed()) {
                $this->setScope($scope);
            }
        }

        $scopeToRestore = null;

        if ($class instanceof ClassType && count($class->genericTypes()) > 0) {
            $templateTags = $this->scope->getTemplateTags();

            if (count($templateTags) === 0 && $reflection->getDocComment()) {
                $this->getDocBlockParser()->parseTemplateTags($reflection->getDocComment());
                $templateTags = $this->scope->getTemplateTags();
            }

            if (count($templateTags) > 0) {
                $genericTypes = $class->genericTypes();
                $overriddenTags = array_map(
                    fn ($index, $tag) => isset($genericTypes[$index])
                        ? new TemplateTagType($tag->name, $genericTypes[$index], $tag->default, $tag->lowerBound, $tag->description)
                        : $tag,
                    array_keys($templateTags),
                    $templateTags,
                );

                $scopeToRestore = $this->scope;
                $tempScope = clone $this->scope;
                $tempScope->setTemplateTags(array_values($overriddenTags));
                $tempScope->setReceiverType($class);
                $this->setScope($tempScope);

                $this->resolveUseTraitBindings($reflection);
            }
        }

        try {
            if ($reflection->isSubclassOf(Model::class) && $this->scope->result()->hasProperty($method)) {
                return [$this->scope->result()->getProperty($method)->type];
            }

            $returnTypes = [];

            if ($reflection->hasMethod($method)) {
                $methodReflection = $reflection->getMethod($method);

                // When we have a temp scope with class-level template bindings, also resolve
                // @extends chain for inherited methods and bind method-level template tags.
                if ($scopeToRestore !== null) {
                    $declaringClassName = $methodReflection->getDeclaringClass()->getName();
                    if ($declaringClassName !== $reflection->getName() && $reflection->getDocComment()) {
                        $this->resolveExtendsChain($reflection, $declaringClassName);
                    }

                    if ($methodReflection->getDocComment()) {
                        $this->bindMethodLevelTemplateTags($methodReflection->getDocComment());
                        if (! empty($closureReturnTypes)) {
                            $this->bindCallableArgTemplates($methodReflection->getDocComment(), $methodReflection, $closureReturnTypes);
                        }
                    }
                }

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
                $builderType = (new ClassType(Builder::class))->setGenericTypes([new ClassType($className)]);
                array_push(
                    $returnTypes,
                    ...$this->methodReturnType($builderType, $method, $node),
                );
            }

            if (count($returnTypes) === 0 && $reflection->getDocComment()) {
                $mixins = $this->getDocBlockParser()->parseMixins($reflection->getDocComment());

                foreach ($mixins as $mixin) {
                    if (! $mixin instanceof ClassType) {
                        continue;
                    }

                    try {
                        $mixinReflection = $this->reflectClass($mixin->value);
                    } catch (Throwable $e) {
                        continue;
                    }

                    if ($mixinReflection->hasMethod($method)) {
                        array_push($returnTypes, ...$this->methodReturnType($mixin->value, $method, $node));
                        break;
                    }
                }
            }

            if (count($returnTypes) > 0) {
                return $returnTypes;
            }

            if (! $node || ! $reflection->isInstantiable() || ! $this->hasMacro($className, $node)) {
                return [Type::mixed()];
            }

            return $this->cachedMacros[$className][$node->name->name] ??= $this->resolveMacro($reflection, $node->name->name);
        } finally {
            if ($scopeToRestore !== null) {
                $this->setScope($scopeToRestore);
            }
        }
    }

    protected function resolveUseTraitBindings(ReflectionClass $reflection): void
    {
        $fileName = $reflection->getFileName();

        if (! $fileName || ! file_exists($fileName)) {
            return;
        }

        $useBindings = $this->parseTraitUseBindings($reflection, $fileName);

        if (empty($useBindings)) {
            return;
        }

        $additionalTags = [];

        foreach ($useBindings as $traitName => $boundTypes) {
            try {
                $traitReflection = $this->reflectClass($traitName);
            } catch (Throwable $e) {
                continue;
            }

            $traitParamNames = $this->getDocBlockParser()->getTemplateTagNames(
                $traitReflection->getDocComment() ?: ''
            );

            foreach ($traitParamNames as $index => $paramName) {
                $boundType = $boundTypes[$index] ?? null;

                if ($boundType !== null && ! $this->scope->getTemplateTag($paramName)) {
                    $additionalTags[] = new TemplateTagType($paramName, $boundType, null, null, null);
                }
            }
        }

        if (! empty($additionalTags)) {
            $this->scope->setTemplateTags([...$this->scope->getTemplateTags(), ...$additionalTags]);
        }
    }

    /**
     * Parse the PHP source file of a class to extract @use Trait<Type> bindings
     * declared on trait use statements.
     *
     * Returns an array keyed by fully-qualified trait name, with values being
     * arrays of resolved Surveyor Type objects corresponding to the bound generic
     * type arguments.
     *
     * @return array<string, list<TypeContract>>
     */
    protected function parseTraitUseBindings(ReflectionClass $reflection, string $fileName): array
    {
        try {
            $nodes = $this->getParser()->parseFile($fileName);
        } catch (Throwable $e) {
            return [];
        }

        $bindings = [];
        $nodeFinder = new NodeFinder;

        /** @var TraitUse[] $traitUseNodes */
        $traitUseNodes = $nodeFinder->findInstanceOf($nodes, TraitUse::class);

        foreach ($traitUseNodes as $traitUseNode) {
            $docComment = $traitUseNode->getDocComment();

            if (! $docComment || ! str_contains($docComment->getText(), '@use')) {
                continue;
            }

            $useTypes = $this->getDocBlockParser()->parseUsesTags($docComment->getText());

            foreach ($useTypes as $useType) {
                if (! $useType instanceof ClassType || count($useType->genericTypes()) === 0) {
                    continue;
                }

                $bindings[$useType->value] = $useType->genericTypes();
            }
        }

        return $bindings;
    }

    protected function resolveMacro(ReflectionClass $reflection, string $macroName): array
    {
        $analyzed = $this->getAnalyzer()->analyze($reflection->getFileName());

        $reflectionProperty = $reflection->getProperty('macros');
        $macros = $reflectionProperty->getValue($reflection);

        $funcReflection = new ReflectionFunction($macros[$macroName]);
        $analyzed = $this->getAnalyzer()->analyze($funcReflection->getFilename());

        if ($macroResolution = $analyzed->analyzed()->macro($reflection->getName(), $macroName)) {
            return [$macroResolution];
        }

        return [Type::mixed()];
    }

    public function returnType(ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType $returnType): ?TypeContract
    {
        if ($returnType instanceof ReflectionNamedType) {
            if (in_array($returnType->getName(), ['static', 'self'])) {
                if ($receiverType = $this->scope->getReceiverType()) {
                    return clone $receiverType;
                }

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

        return $this->cachedClasses[$className] ??= $this->resolveReflectedClass($className);
    }

    protected function resolveReflectedClass(string $className): ReflectionClass
    {
        $className = Util::resolveValidClass($className, $this->scope);
        $className = Util::resolveClass($className);

        if (! Util::isClassOrInterface($className)) {
            throw new Exception('Class does not exist: '.$className);
        }

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
