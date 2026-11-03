<?php

namespace App\Spreading;

class Circular
{
    public function first(): array
    {
        return [
            'first' => 'a',
            ...$this->second(),
        ];
    }

    public function second(): array
    {
        return [
            'second' => 'b',
            ...$this->first(),
        ];
    }
}
