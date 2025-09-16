<?php

namespace Laravel\Surveyor\Types\Contracts;

interface CollapsibleType
{
    public function collapse(): Type;
}
