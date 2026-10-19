<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;

class ArrayableList implements Arrayable
{
    /** @var list<int> */
    public array $numbers;

    /** @return list<int> */
    public function toArray(): array
    {
        return $this->numbers;
    }
}
