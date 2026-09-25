<?php

namespace Laravel\Surveyor\Analyzer;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;
use JsonSerializable;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\Entities\JsonEncoded;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;
use ReflectionClass;
use Throwable;

class ArrayableResolver
{
    public function __construct(
        protected Analyzer $analyzer,
    ) {
        //
    }

    /**
     * Resolve a ClassType to its array shape if it implements Arrayable or JsonSerializable.
     * Uses runtime reflection (class_implements) to catch inherited interfaces.
     * Returns null if the class is not resolvable.
     */
    public function resolve(TypeContract $type): ?TypeContract
    {
        return $this->resolveUsing($type, [
            Arrayable::class => 'toArray',
            JsonSerializable::class => 'jsonSerialize',
        ]);
    }

    /**
     * Resolve the data a JsonResponse sends for a ClassType. Like JsonResponse,
     * this prefers Jsonable, then JsonSerializable, then Arrayable.
     */
    public function resolveJsonResponse(TypeContract $type): ?TypeContract
    {
        return $this->resolveUsing($type, [
            Jsonable::class => 'toJson',
            JsonSerializable::class => 'jsonSerialize',
            Arrayable::class => 'toArray',
        ]);
    }

    /**
     * @param  array<class-string, string>  $serializers
     */
    protected function resolveUsing(TypeContract $type, array $serializers): ?TypeContract
    {
        if (! $type instanceof ClassType) {
            return null;
        }

        $className = $type->resolved();

        if (! class_exists($className)) {
            return null;
        }

        try {
            $interfaces = class_implements($className) ?: [];
        } catch (Throwable $e) {
            return null;
        }

        $serializers = array_filter(
            $serializers,
            fn ($interface) => in_array($interface, $interfaces),
            ARRAY_FILTER_USE_KEY,
        );

        if ($serializers === []) {
            return null;
        }

        try {
            if (AnalyzedCache::isInProgress($file = (new ReflectionClass($className))->getFileName())) {
                AnalyzedCache::noteCycle($file);

                return null;
            }
        } catch (Throwable $e) {
            return null;
        }

        $analyzed = $this->analyzer->analyzeClass($className)->result();

        if (! $analyzed instanceof ClassLikeResult) {
            return null;
        }

        foreach ($serializers as $method) {
            if ($resolved = $this->serializedType($className, $analyzed, $method)) {
                return $resolved;
            }
        }

        return null;
    }

    protected function serializedType(string $class, ClassLikeResult $result, string $method): ?TypeContract
    {
        $inherited = ! $result->hasMethod($method);

        $type = $this->methodType($class, $result, $method);

        if ($method === 'toJson') {
            $type = $type === null ? null : $this->decodeJsonType($type);
        } elseif (! $type instanceof ArrayType && ! $type instanceof ArrayShapeType) {
            return null;
        }

        if ($type === null) {
            return null;
        }

        // An inherited serializer is usually a base class's generic one, such as
        // Fluent's or one built on get_object_vars(). Its unkeyed array says
        // nothing about the subclass, so only a shape with known keys is kept.
        return ! $inherited || $this->describesShape($type) ? $type : null;
    }

    protected function describesShape(TypeContract $type): bool
    {
        if ($type instanceof UnionType) {
            return array_filter($type->types, fn ($member) => ! $this->describesShape($member)) === [];
        }

        return $type instanceof ArrayType && $type->value !== [];
    }

    protected function methodType(string $class, ClassLikeResult $result, string $method): ?TypeContract
    {
        if (! $result->hasMethod($method)) {
            $declaringClass = (new ReflectionClass($class))->getMethod($method)->getDeclaringClass();

            // Their generic framework serializer cannot describe a particular model or collection.
            if (is_a($class, Model::class, true) || is_a($class, Enumerable::class, true)) {
                return null;
            }

            if (AnalyzedCache::isInProgress($file = $declaringClass->getFileName())) {
                AnalyzedCache::noteCycle($file);

                return null;
            }

            $result = $this->analyzer->analyzeClass($declaringClass->getName())->result();
        }

        return $result instanceof ClassLikeResult && $result->hasMethod($method)
            ? $result->getMethod($method)->returnType()
            : null;
    }

    /**
     * The data behind a toJson() return type: what was handed to json_encode(),
     * or a literal JSON string decoded. Returns null for any other string.
     */
    protected function decodeJsonType(TypeContract $type): ?TypeContract
    {
        if ($type instanceof JsonEncoded) {
            return $type->data;
        }

        if ($type instanceof UnionType) {
            // An encoding failure has no JSON payload. Resolve the successful
            // serialization while keeping false in ordinary function inference.
            $members = array_filter($type->types, fn ($member) => ! ($member instanceof BoolType && $member->value === false));
            $types = array_map(fn ($member) => $this->decodeJsonType($member), $members);

            return $types === [] || in_array(null, $types, true) ? null : Type::union(...$types);
        }

        if ($type instanceof StringType && $type->value !== null) {
            $decoded = json_decode($type->value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->jsonValueType($decoded);
            }
        }

        return null;
    }

    protected function jsonValueType(mixed $value): TypeContract
    {
        if (is_string($value)) {
            return new StringType($value);
        }

        return is_array($value)
            ? Type::array(array_map(fn ($item) => $this->jsonValueType($item), $value))
            : Type::from($value);
    }
}
