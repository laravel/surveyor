<?php

namespace App\Spreading;

class Recursive
{
    public function ancestors(int $depth): array
    {
        if ($depth === 0) {
            return [];
        }

        return array_merge(['ancestor'], $this->ancestors($depth - 1));
    }
}
