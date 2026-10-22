<?php

namespace Laravel\Surveyor\Analyzer;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Laravel\Surveyor\Analyzed\ClassLikeResult;
use Laravel\Surveyor\Types\ArrayShapeType;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use Laravel\Surveyor\Types\MixedType;
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
     * Resolve a ClassType to its serialized type if it implements Arrayable or JsonSerializable.
     * Uses runtime reflection (class_implements) to catch inherited interfaces.
     * Returns null if the class is not resolvable.
     */
    public function resolve(TypeContract $type): ?TypeContract
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

        $isArrayable = in_array(Arrayable::class, $interfaces);
        $isJsonSerializable = in_array(JsonSerializable::class, $interfaces);

        if (! $isArrayable && ! $isJsonSerializable) {
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

        if ($isArrayable && $analyzed->hasMethod('toArray')) {
            $returnType = $analyzed->getMethod('toArray')->returnType();

            if ($returnType instanceof ArrayType || $returnType instanceof ArrayShapeType) {
                return $returnType;
            }
        }

        if ($isJsonSerializable && $analyzed->hasMethod('jsonSerialize')) {
            $returnType = $analyzed->getMethod('jsonSerialize')->returnType();

            if (! $returnType instanceof MixedType) {
                return $returnType;
            }
        }

        return null;
    }
}
