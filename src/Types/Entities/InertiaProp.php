<?php

namespace Laravel\Surveyor\Types\Entities;

use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type as TypeContract;

/**
 * An Inertia prop helper (Inertia::defer(), once(), merge(), ...) and the
 * value it sends to the page. Its configuration methods return the same
 * prop, so chained calls keep the value instead of calling into it.
 */
class InertiaProp extends ClassType implements TypeContract
{
    public function __construct(
        string $class,
        public readonly TypeContract $data,
    ) {
        parent::__construct($class);
    }

    public function id(): string
    {
        return 'inertia-prop<'.parent::id().','.$this->data->id().'>';
    }
}
