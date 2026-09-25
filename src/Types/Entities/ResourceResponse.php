<?php

namespace Laravel\Surveyor\Types\Entities;

use Illuminate\Http\Resources\Attributes\PreserveKeys;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;
use ReflectionClass;

class ResourceResponse extends ClassType
{
    /**
     * The resource's own with() data, which additional() data is merged into.
     */
    protected ?TypeContract $withData;

    public function __construct(
        public readonly string $resourceClass,
        public readonly TypeContract $data,
        public readonly bool $isCollection = false,
        public readonly ?TypeContract $additional = null,
        public readonly ?TypeContract $pagination = null,
        public readonly ?bool $preserveKeys = null,
    ) {
        parent::__construct($resourceClass);

        $this->withData = $additional;
    }

    /**
     * The resource Laravel turns into the response. Resource::collection()
     * responds through an anonymous collection, which has its own wrapping.
     */
    public function responseClass(): string
    {
        return $this->isCollection && ! is_subclass_of($this->resourceClass, ResourceCollection::class)
            ? AnonymousResourceCollection::class
            : $this->resourceClass;
    }

    /**
     * The key the data is wrapped in. Wrapping is runtime state, often set by
     * withoutWrapping() in a service provider, so it is read when asked for
     * rather than stored with an analysis that may come from the cache.
     */
    public function wrap(): ?string
    {
        $class = $this->responseClass();

        return class_exists($class) ? $class::$wrap : 'data';
    }

    /**
     * Whether the data is wrapped even when it already has a key named after
     * the wrapper.
     */
    public function forcesWrapping(): bool
    {
        $class = $this->responseClass();

        return property_exists($class, 'forceWrapping') && (bool) $class::$forceWrapping;
    }

    /**
     * Whether a collection keeps its items' keys rather than becoming a list,
     * set by preserveKeys() or by the resource's #[PreserveKeys] or $preserveKeys.
     */
    public function preservesKeys(): bool
    {
        if (! $this->isCollection) {
            return false;
        }

        if ($this->preserveKeys !== null) {
            return $this->preserveKeys;
        }

        if (! class_exists($this->resourceClass)) {
            return false;
        }

        $reflection = new ReflectionClass($this->resourceClass);

        if (class_exists(PreserveKeys::class) && $reflection->getAttributes(PreserveKeys::class) !== []) {
            return true;
        }

        return $reflection->hasProperty('preserveKeys')
            && $reflection->getProperty('preserveKeys')->getDefaultValue() === true;
    }

    public function withAdditional(ArrayType $additional): self
    {
        return $this->copy(additional: $this->withData instanceof ArrayType
            ? $this->mergeAdditional($this->withData, $additional)
            : $additional);
    }

    public function withPreservedKeys(bool $preserveKeys = true): self
    {
        return $this->copy(preserveKeys: $preserveKeys);
    }

    protected function copy(mixed ...$arguments): self
    {
        $response = new self(...[
            'resourceClass' => $this->resourceClass,
            'data' => $this->data,
            'isCollection' => $this->isCollection,
            'additional' => $this->additional,
            'pagination' => $this->pagination,
            'preserveKeys' => $this->preserveKeys,
            ...$arguments,
        ]);

        $response->withData = $this->withData;

        return $response->nullable($this->isNullable())->required(! $this->isOptional());
    }

    /**
     * Merge the way array_merge_recursive() does when Laravel builds the response.
     */
    protected function mergeAdditional(ArrayType $first, ArrayType $second): ArrayType
    {
        $values = $first->value;

        foreach ($second->value as $key => $value) {
            if (is_int($key)) {
                $values[] = $value;
            } elseif (! isset($values[$key])) {
                $values[$key] = $value;
            } else {
                $values[$key] = $this->mergeAdditional(
                    $values[$key] instanceof ArrayType ? $values[$key] : new ArrayType([$values[$key]]),
                    $value instanceof ArrayType ? $value : new ArrayType([$value]),
                );
            }
        }

        return new ArrayType($values);
    }

    public function id(): string
    {
        $id = $this->resourceClass.'::'.$this->data->id().'::'.$this->additional?->id();

        if ($this->pagination !== null) {
            $id .= '::'.$this->pagination->id();
        }

        if ($this->preserveKeys !== null) {
            $id .= '::keys:'.var_export($this->preserveKeys, true);
        }

        return $this->isCollection ? $id.'[]' : $id;
    }
}
