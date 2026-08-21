<?php

namespace App\Models;

use App\Models\Concerns\HasNotes;

class Note extends BaseNote
{
    use HasNotes;

    protected $table = 'notes';
}
