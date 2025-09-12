<?php

namespace Laravel\Surveyor\Types;

use Laravel\Surveyor\Debug\Debug;

class StringType extends AbstractType implements Contracts\Type
{
    public function __construct(public readonly ?string $value = null)
    {
        //
        if ($value === 'static') {
            Debug::ddAndOpen($this, $this, 'static');
        }
    }

    public function id(): string
    {
        return $this->value === null ? 'null' : $this->value;
    }
}
