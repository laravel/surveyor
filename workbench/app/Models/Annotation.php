<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property list<string> $languages
 * @property array<string, int> $scores
 */
class Annotation extends Model
{
    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'scores' => 'array',
            'meta' => 'array',
        ];
    }
}
