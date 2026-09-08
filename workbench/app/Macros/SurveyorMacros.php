<?php

namespace App\Macros;

use Illuminate\Support\Str;

class SurveyorMacros
{
    public static function register(): void
    {
        Str::macro('surveyorShout', fn (string $value): string => strtoupper($value));
    }
}
