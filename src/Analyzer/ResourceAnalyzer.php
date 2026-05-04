<?php

namespace Laravel\Surveyor\Analyzer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Debug\Debug;
use Laravel\Surveyor\Parser\DocBlockParser;
use Laravel\Surveyor\Reflector\Reflector;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\JsonApiResourceResponse;
use Laravel\Surveyor\Types\Entities\ResourceResponse;
use Laravel\Surveyor\Types\Type;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

class ResourceAnalyzer
{
    /** @var array<string, ResourceResponse|JsonApiResourceResponse> */
    protected array $responses = [];

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

        $analyzed = $this->analyzer->analyzeClass($modelClass);
        $modelResult = $analyzed->result();

        if (! $modelResult instanceof ClassResult) {
            return;
        }

        foreach ($modelResult->publicProperties() as $property) {
            $scope->state()->properties()->addManually(
                $property->name,
                $property->type,
                0,
                0,
                0,
                0
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

        $this->responses[$resource] = new ResourceResponse(
            resourceClass: $resource,
            data: $data,
            isCollection: $this->isResourceCollection($resource),
            wrap: $wrap,
            additional: $additional,
        );
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

        // Reuse the response cached during Phase B if analysis already produced one
        if ($existing = $this->responses[$resourceClass] ?? null) {
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
        return $this->resolveFromMixin($resource)
            ?? $this->resolveFromConstructor($resource)
            ?? $this->resolveFromNamingConvention($resource);
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
            // No usable @mixin docblock; fall through to next strategy.
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

                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    $typeName = $type->getName();

                    if (class_exists($typeName) && is_subclass_of($typeName, Model::class)) {
                        return $typeName;
                    }
                }
            }
        } catch (Throwable $e) {
            // No constructor-typed model param; fall through to next strategy.
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
            Debug::error($e, 'Reading $wrap property on resource');
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
            Debug::error($e, 'Resolving collected resource');
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
        $this->responses[$resource] = new JsonApiResourceResponse(
            resourceClass: $resource,
            attributes: $this->resolveJsonApiAttributes($resource, $result, $scope),
            relationships: $this->resolveJsonApiRelationships($resource, $result, $scope),
            links: $this->resolveJsonApiMethodShape($result, 'toLinks'),
            meta: $this->resolveJsonApiMethodShape($result, 'toMeta'),
            isCollection: false,
            additional: $this->resolveWithMethod($result),
        );
    }

    protected function resolveJsonApiAttributes(string $resource, ClassResult $result, Scope $scope): ?TypeContract
    {
        // 1. Check for $attributes property (list of field names). Walks the inheritance
        // chain so a base resource declaring shared attributes is honored.
        try {
            $reflection = new ReflectionClass($resource);

            if ($reflection->hasProperty('attributes')) {
                $attrValue = $reflection->getProperty('attributes')->getDefaultValue();

                if (is_array($attrValue) && ! empty($attrValue)) {
                    return $this->resolveAttributeListToTypes($attrValue, $scope);
                }
            }
        } catch (Throwable $e) {
            // No usable $attributes property; fall through to toAttributes() method.
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

    protected function resolveJsonApiRelationships(string $resource, ClassResult $result, Scope $scope): ?TypeContract
    {
        // 1. Check for $relationships property. Walks the inheritance chain so a base
        // resource declaring shared relationships is honored.
        try {
            $reflection = new ReflectionClass($resource);

            if ($reflection->hasProperty('relationships')) {
                $relValue = $reflection->getProperty('relationships')->getDefaultValue();

                if (is_array($relValue) && ! empty($relValue)) {
                    return $this->resolveRelationshipListToTypes($relValue, $scope);
                }
            }
        } catch (Throwable $e) {
            // No usable $relationships property; fall through to toRelationships() method.
        }

        // 2. Check toRelationships() method return type
        if ($result->hasMethod('toRelationships')) {
            $returnType = $result->getMethod('toRelationships')->returnType();

            if ($returnType instanceof ArrayType) {
                // The method's array shape gives us relationship NAMES as keys; the values
                // (e.g. PostApiResource::class) are not the wire shape. Re-wrap into
                // identifier shapes the same way the $relationships property path does.
                return $this->resolveRelationshipListToTypes(array_keys($returnType->value), $scope);
            }
        }

        return null;
    }

    /**
     * Resolve a relationship list to JSON:API identifier shapes.
     * - to-one: { data: { id: string, type: string } | null }
     * - to-many: { data: [{ id: string, type: string }] }
     * Cardinality is detected by looking up the relationship name on the scope's
     * model properties (an ArrayShapeType indicates a to-many relation).
     */
    protected function resolveRelationshipListToTypes(array $relationships, Scope $scope): ArrayType
    {
        $typed = [];

        foreach ($relationships as $key => $value) {
            // Could be ['author'] (indexed) or ['author' => UserResource::class] (keyed)
            $name = is_int($key) ? $value : $key;
            $typed[$name] = $this->jsonApiRelationshipIdentifier($this->isToManyRelation($name, $scope));
        }

        return new ArrayType($typed);
    }

    protected function isToManyRelation(string $name, Scope $scope): bool
    {
        return $scope->state()->properties()->get($name) instanceof ArrayShapeType;
    }

    /**
     * JSON:API relationship identifier shape.
     * to-one  → { data: { id, type } | null }
     * to-many → { data: [{ id, type }] }
     */
    protected function jsonApiRelationshipIdentifier(bool $toMany = false): ArrayType
    {
        $identifier = new ArrayType([
            'id' => Type::string(),
            'type' => Type::string(),
        ]);

        if ($toMany) {
            return new ArrayType([
                'data' => Type::arrayShape(Type::int(), $identifier),
            ]);
        }

        $identifier->nullable(true);

        return new ArrayType([
            'data' => $identifier,
        ]);
    }

    protected function resolveJsonApiMethodShape(ClassResult $result, string $method): ?TypeContract
    {
        if (! $result->hasMethod($method)) {
            return null;
        }

        $returnType = $result->getMethod($method)->returnType();

        return $returnType instanceof ArrayType ? $returnType : null;
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

        $existing = $this->responses[$resourceClass] ?? null;

        if ($existing instanceof JsonApiResourceResponse) {
            if ($isCollection && ! $existing->isCollection) {
                return new JsonApiResourceResponse(
                    resourceClass: $existing->resourceClass,
                    attributes: $existing->attributes,
                    relationships: $existing->relationships,
                    links: $existing->links,
                    meta: $existing->meta,
                    isCollection: true,
                    additional: $existing->additional,
                );
            }

            return $existing;
        }

        return null;
    }
}
