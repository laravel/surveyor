<?php

namespace Laravel\StaticAnalyzer\Types;

class MixedType extends AbstractType implements Contracts\Type
{
    public function id(): string
    {
        return 'mixed';
    }
}
