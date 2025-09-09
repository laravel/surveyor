<?php

namespace Laravel\StaticAnalyzer\Types;

use Laravel\StaticAnalyzer\Debug\Debug;

class StringType extends AbstractType implements Contracts\Type
{
    public function __construct(public readonly ?string $value = null)
    {
        //
        if ($value === 'static') {
            Debug::ddFromClass($this, $this, 'static');
        }
    }

    public function id(): string
    {
        return $this->value === null ? 'null' : $this->value;
    }
}
