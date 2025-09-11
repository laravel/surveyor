<?php

namespace Laravel\Surveyor\Types;

class MixedType extends AbstractType
{
    public function id(): string
    {
        return 'mixed';
    }
}
