<?php

namespace Laravel\Surveyor\Types\Contracts;

interface MultiType
{
    /**
     * @return Type[]
     */
    public function getTypes(): array;
}
