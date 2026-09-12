<?php

namespace Laravel\Surveyor\Types\Entities;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;

class ResourceResponse extends ClassType
{
    public function __construct(
        public readonly string $resourceClass,
        public readonly TypeContract $data,
        public readonly bool $isCollection = false,
        public readonly ?string $wrap = 'data',
        public readonly ?TypeContract $additional = null,
    ) {
        parent::__construct($resourceClass);
    }

    public function id(): string
    {
        $id = $this->resourceClass.'::'.$this->data->id();

        return $this->isCollection ? $id.'[]' : $id;
    }

    public function isMoreSpecificThan(TypeContract $type): bool
    {
        return $type::class === ClassType::class
            && ($this->resolved() === $type->resolved()
                || ($this->isCollection && ! is_a($this->resolved(), ResourceCollection::class, true) && $type->resolved() === AnonymousResourceCollection::class));
    }
}
