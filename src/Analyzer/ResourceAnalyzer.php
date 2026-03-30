<?php

namespace Laravel\Surveyor\Analyzer;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzed\PropertyResult;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\Type;
use ReflectionClass;
use Throwable;

class ResourceAnalyzer
{
    protected ?string $resolvedModelClass = null;

    public function __construct(
        protected Reflector $reflector,
        protected Analyzer $analyzer,
        protected DocBlockParser $docBlockParser,
    ) {
        //
    }

    /**
     * Phase A: Inject model properties into the resource scope so that
     * $this->property references resolve correctly during toArray() analysis.
     *
     * Called on class ENTER (before method bodies are walked).
     */
    public function injectModelProperties(string $resource, ClassResult $result, Scope $scope): void
    {
        $this->reflector->setScope($scope);

        $modelClass = $this->resolveModelClass($resource, $result, $scope);

        if (! $modelClass || ! class_exists($modelClass)) {
            return;
        }

        $this->resolvedModelClass = $modelClass;

        $analyzed = $this->analyzer->analyzeClass($modelClass);
        $modelResult = $analyzed->result();

        if (! $modelResult instanceof ClassResult) {
            return;
        }

        foreach ($modelResult->publicProperties() as $property) {
            $scope->state()->properties()->addManually(
                $property->name, $property->type, 0, 0, 0, 0
            );
        }
    }

    /**
     * Phase B: After toArray() has been walked, extract the resolved data shape
     * and store resource metadata on the ClassResult.
     *
     * Called on class EXIT (after method bodies have been walked).
     */
    public function resolveDataShape(string $resource, ClassResult $result, Scope $scope): void
    {
        $this->reflector->setScope($scope);

        $data = $this->extractToArrayShape($resource, $result);

        if (! $data) {
            return;
        }

        $wrap = $this->resolveWrapKey($resource);
        $additional = $this->resolveWithMethod($result);

        $resourceResponse = new ResourceResponse(
            resourceClass: $resource,
            data: $data,
            isCollection: $this->isResourceCollection($resource),
            wrap: $wrap,
            additional: $additional,
        );

        // Store as a synthetic method return type so downstream consumers can retrieve it
        $result->setResourceResponse($resourceResponse);
    }

    /**
     * Build a ResourceResponse for a resource class that has already been analyzed.
     */
    public function buildResourceResponse(string $resourceClass, bool $isCollection = false): ?ResourceResponse
    {
        if (! class_exists($resourceClass)) {
            return null;
        }

        if (AnalyzedCache::isInProgress((new ReflectionClass($resourceClass))->getFileName())) {
            return null;
        }

        $analyzed = $this->analyzer->analyzeClass($resourceClass);
        $result = $analyzed->result();

        if (! $result instanceof ClassResult) {
            return null;
        }

        // If the ClassResult already has a ResourceResponse from Phase B, use it
        if ($existing = $result->resourceResponse()) {
            if ($isCollection && ! $existing->isCollection) {
                return new ResourceResponse(
                    resourceClass: $existing->resourceClass,
                    data: $existing->data,
                    isCollection: true,
                    wrap: $existing->wrap,
                    additional: $existing->additional,
                );
            }

            return $existing;
        }

        // Fallback: try to extract toArray shape directly
        $data = $this->extractToArrayShape($resourceClass, $result);

        if (! $data) {
            return null;
        }

        return new ResourceResponse(
            resourceClass: $resourceClass,
            data: $data,
            isCollection: $isCollection || $this->isResourceCollection($resourceClass),
            wrap: $this->resolveWrapKey($resourceClass),
            additional: $this->resolveWithMethod($result),
        );
    }

    protected function extractToArrayShape(string $resource, ClassResult $result): ?TypeContract
    {
        if ($result->hasMethod('toArray')) {
            $returnType = $result->getMethod('toArray')->returnType();

            if ($returnType instanceof ArrayType) {
                return $returnType;
            }
        }

        // For ResourceCollection without toArray, the shape is an array of the collected resource
        if ($this->isResourceCollection($resource)) {
            $collectedResource = $this->resolveCollectedResource($resource);

            if ($collectedResource) {
                $innerResponse = $this->buildResourceResponse($collectedResource);

                if ($innerResponse) {
                    return $innerResponse->data;
                }
            }
        }

        return null;
    }

    protected function resolveModelClass(string $resource, ClassResult $result, Scope $scope): ?string
    {
        // 1. Check @mixin docblock
        $modelFromMixin = $this->resolveFromMixin($resource);
        if ($modelFromMixin) {
            return $modelFromMixin;
        }

        // 2. Check constructor parameter type hints
        $modelFromConstructor = $this->resolveFromConstructor($resource);
        if ($modelFromConstructor) {
            return $modelFromConstructor;
        }

        // 3. Naming convention fallback
        return $this->resolveFromNamingConvention($resource);
    }

    protected function resolveFromMixin(string $resource): ?string
    {
        try {
            $reflection = new ReflectionClass($resource);
            $docComment = $reflection->getDocComment();

            if (! $docComment) {
                return null;
            }

            $mixins = $this->docBlockParser->parseMixins($docComment);

            foreach ($mixins as $mixin) {
                if ($mixin instanceof ClassType) {
                    $resolved = $mixin->resolved();

                    if (class_exists($resolved) && is_subclass_of($resolved, \Illuminate\Database\Eloquent\Model::class)) {
                        return $resolved;
                    }
                }
            }
        } catch (Throwable $e) {
            // Unable to parse docblock
        }

        return null;
    }

    protected function resolveFromConstructor(string $resource): ?string
    {
        try {
            $reflection = new ReflectionClass($resource);

            // Check the resource's own constructor, not the parent
            if (! $reflection->getConstructor() || $reflection->getConstructor()->getDeclaringClass()->getName() !== $resource) {
                return null;
            }

            foreach ($reflection->getConstructor()->getParameters() as $param) {
                $type = $param->getType();

                if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                    $typeName = $type->getName();

                    if (class_exists($typeName) && is_subclass_of($typeName, \Illuminate\Database\Eloquent\Model::class)) {
                        return $typeName;
                    }
                }
            }
        } catch (Throwable $e) {
            // Unable to reflect constructor
        }

        return null;
    }

    protected function resolveFromNamingConvention(string $resource): ?string
    {
        $baseName = class_basename($resource);

        // Strip Resource or Collection suffix
        $modelName = preg_replace('/(Resource|Collection)$/', '', $baseName);

        if (! $modelName || $modelName === $baseName) {
            return null;
        }

        // Try common model namespace patterns
        $namespace = str($resource)->beforeLast('\\')->toString();
        $appNamespace = str($namespace)->before('\\Http\\')->toString();

        $candidates = [
            $appNamespace.'\\Models\\'.$modelName,
            $appNamespace.'\\'.$modelName,
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, \Illuminate\Database\Eloquent\Model::class)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveWrapKey(string $resource): ?string
    {
        try {
            $reflection = new ReflectionClass($resource);

            if ($reflection->hasProperty('wrap')) {
                $property = $reflection->getProperty('wrap');

                if ($property->isStatic()) {
                    return $property->getValue();
                }
            }
        } catch (Throwable $e) {
            // Unable to read $wrap property
        }

        return 'data';
    }

    protected function resolveWithMethod(ClassResult $result): ?TypeContract
    {
        if (! $result->hasMethod('with')) {
            return null;
        }

        $returnType = $result->getMethod('with')->returnType();

        if ($returnType instanceof ArrayType) {
            return $returnType;
        }

        return null;
    }

    protected function isResourceCollection(string $resource): bool
    {
        return is_subclass_of($resource, ResourceCollection::class);
    }

    protected function resolveCollectedResource(string $collectionClass): ?string
    {
        try {
            $reflection = new ReflectionClass($collectionClass);

            // Check $collects property
            if ($reflection->hasProperty('collects')) {
                $property = $reflection->getProperty('collects');
                $value = $property->getDefaultValue();

                if ($value && class_exists($value)) {
                    return $value;
                }
            }

            // Naming convention: UserCollection → UserResource
            $baseName = class_basename($collectionClass);
            $resourceName = preg_replace('/Collection$/', 'Resource', $baseName);

            if ($resourceName !== $baseName) {
                $namespace = str($collectionClass)->beforeLast('\\')->toString();
                $candidate = $namespace.'\\'.$resourceName;

                if (class_exists($candidate) && is_subclass_of($candidate, JsonResource::class)) {
                    return $candidate;
                }
            }
        } catch (Throwable $e) {
            // Unable to resolve collected resource
        }

        return null;
    }
}
