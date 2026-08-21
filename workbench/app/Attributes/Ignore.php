<?php

namespace App\Attributes;

use Attribute;
use Laravel\Surveyor\Contracts\Ignored;

#[Attribute(Attribute::TARGET_ALL)]
class Ignore implements Ignored
{
    //
}
