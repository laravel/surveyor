<?php

namespace App\Support;

use App\Http\Resources\CustomPayloadCollection;
use App\Http\Resources\MethodCallResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResourceMethodCalls
{
    public function before()
    {
        return $this->collection();
    }

    public function chained()
    {
        return $this->forward();
    }

    public function forward(): AnonymousResourceCollection
    {
        return $this->collection();
    }

    public function staticCall()
    {
        return self::collection();
    }

    public function recursiveCall()
    {
        return $this->recursive(false);
    }

    public function nullableCall()
    {
        return $this->nullable(false);
    }

    public function collection(): AnonymousResourceCollection
    {
        return MethodCallResource::collection([]);
    }

    public function collectedNamed(): AnonymousResourceCollection
    {
        return CustomPayloadCollection::collection([[null]]);
    }

    public function collectedNamedCall()
    {
        return $this->collectedNamed();
    }

    public function collectedNamedFluent()
    {
        return $this->collectedNamed()->additional(['source' => 'test']);
    }

    public function collectedNamedResolved()
    {
        return $this->collectedNamed()->additional(['source' => 'test'])->resolve();
    }

    public function recursive(bool $repeat): AnonymousResourceCollection
    {
        if ($repeat) {
            return $this->recursive(false);
        }

        return MethodCallResource::collection([]);
    }

    public function nullable(bool $include): ?AnonymousResourceCollection
    {
        if (! $include) {
            return null;
        }

        return MethodCallResource::collection([]);
    }

    public function after()
    {
        return $this->collection();
    }

    public function external(ResourceProvider $provider)
    {
        return $provider->collection();
    }

    public function groups(ResourceProvider $provider)
    {
        return $provider->groups();
    }

    public function conditionalGroups(bool $available): array
    {
        if ($available) {
            return ['items' => MethodCallResource::collection([])];
        }

        return ['error' => 'unavailable'];
    }

    public function conditionalGroupsCall()
    {
        return $this->conditionalGroups(false);
    }
}
