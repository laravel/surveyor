<?php

namespace Laravel\Surveyor\Analyzer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\JsonApiResourceResponse;
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

        if ($this->isJsonApiResource($resource)) {
            $this->resolveJsonApiDataShape($resource, $result, $scope);

            return;
        }

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
    public function buildResourceResponse(string $resourceClass, bool $isCollection = false): ResourceResponse|JsonApiResourceResponse|null
    {
        if (! class_exists($resourceClass)) {
            return null;
        }

        if ($this->isJsonApiResource($resourceClass)) {
            return $this->buildJsonApiResourceResponse($resourceClass, $isCollection);
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

                    if (class_exists($resolved) && is_subclass_of($resolved, Model::class)) {
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

                    if (class_exists($typeName) && is_subclass_of($typeName, Model::class)) {
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
            if (class_exists($candidate) && is_subclass_of($candidate, Model::class)) {
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

    // ──────────────────────────────────────────────
    // JSON:API Resource Support
    // ──────────────────────────────────────────────

    protected function isJsonApiResource(string $resource): bool
    {
        return class_exists($resource) && is_subclass_of($resource, JsonApiResource::class);
    }

    protected function resolveJsonApiDataShape(string $resource, ClassResult $result, Scope $scope): void
    {
        $attributes = $this->resolveJsonApiAttributes($resource, $result, $scope);
        $relationships = $this->resolveJsonApiRelationships($resource, $result);
        $links = $this->resolveJsonApiMethodShape($result, 'toLinks');
        $meta = $this->resolveJsonApiMethodShape($result, 'toMeta');

        $response = new JsonApiResourceResponse(
            resourceClass: $resource,
            attributes: $attributes,
            relationships: $relationships,
            links: $links,
            meta: $meta,
            isCollection: false,
        );

        $result->setResourceResponse($response);
    }

    protected function resolveJsonApiAttributes(string $resource, ClassResult $result, Scope $scope): ?TypeContract
    {
        // 1. Check for $attributes property (list of field names)
        try {
            $reflection = new ReflectionClass($resource);

            if ($reflection->hasProperty('attributes') && $reflection->getProperty('attributes')->getDeclaringClass()->getName() === $resource) {
                $attrProperty = $reflection->getProperty('attributes');
                $attrValue = $attrProperty->getDefaultValue();

                if (is_array($attrValue) && ! empty($attrValue)) {
                    return $this->resolveAttributeListToTypes($attrValue, $scope);
                }
            }
        } catch (Throwable $e) {
            // Fall through to method-based resolution
        }

        // 2. Check toAttributes() method return type
        if ($result->hasMethod('toAttributes')) {
            $returnType = $result->getMethod('toAttributes')->returnType();

            if ($returnType instanceof ArrayType) {
                return $returnType;
            }
        }

        return null;
    }

    /**
     * Resolve a list of attribute names (e.g. ['title', 'body']) to typed attributes
     * by looking up each name in the model's properties on the scope.
     */
    protected function resolveAttributeListToTypes(array $attributeNames, Scope $scope): ArrayType
    {
        $typed = [];

        foreach ($attributeNames as $name) {
            $property = $scope->state()->properties()->get($name);
            $typed[$name] = $property ?? Type::mixed();
        }

        return new ArrayType($typed);
    }

    protected function resolveJsonApiRelationships(string $resource, ClassResult $result): ?TypeContract
    {
        // 1. Check for $relationships property
        try {
            $reflection = new ReflectionClass($resource);

            if ($reflection->hasProperty('relationships') && $reflection->getProperty('relationships')->getDeclaringClass()->getName() === $resource) {
                $relProperty = $reflection->getProperty('relationships');
                $relValue = $relProperty->getDefaultValue();

                if (is_array($relValue) && ! empty($relValue)) {
                    return $this->resolveRelationshipListToTypes($relValue);
                }
            }
        } catch (Throwable $e) {
            // Fall through
        }

        // 2. Check toRelationships() method return type
        if ($result->hasMethod('toRelationships')) {
            $returnType = $result->getMethod('toRelationships')->returnType();

            if ($returnType instanceof ArrayType) {
                return $returnType;
            }
        }

        return null;
    }

    /**
     * Resolve a relationship list to types. In JSON:API, relationship output is always
     * { data: { id: string, type: string } | null } for each relationship identifier.
     */
    protected function resolveRelationshipListToTypes(array $relationships): ArrayType
    {
        $typed = [];

        foreach ($relationships as $key => $value) {
            // Could be ['author'] (indexed) or ['author' => UserResource::class] (keyed)
            $name = is_int($key) ? $value : $key;
            // Relationship identifiers always have the same shape in JSON:API
            $typed[$name] = Type::mixed();
        }

        return new ArrayType($typed);
    }

    protected function resolveJsonApiMethodShape(ClassResult $result, string $method): ?TypeContract
    {
        if (! $result->hasMethod($method)) {
            return null;
        }

        $returnType = $result->getMethod($method)->returnType();

        if ($returnType instanceof ArrayType && ! empty($returnType->value)) {
            return $returnType;
        }

        return null;
    }

    public function buildJsonApiResourceResponse(string $resourceClass, bool $isCollection = false): ?JsonApiResourceResponse
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

        $existing = $result->resourceResponse();

        if ($existing instanceof JsonApiResourceResponse) {
            if ($isCollection && ! $existing->isCollection) {
                return new JsonApiResourceResponse(
                    resourceClass: $existing->resourceClass,
                    attributes: $existing->attributes,
                    relationships: $existing->relationships,
                    links: $existing->links,
                    meta: $existing->meta,
                    isCollection: true,
                );
            }

            return $existing;
        }

        return null;
    }
}
