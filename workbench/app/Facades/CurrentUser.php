<?php

namespace App\Facades;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

/**
 * A facade that answers one of its calls itself instead of handing it to the
 * object behind it.
 */
class CurrentUser extends Auth
{
    public static function user(): User
    {
        return parent::user() ?? throw new AuthenticationException;
    }

    protected static function check(): string
    {
        return '';
    }
}
