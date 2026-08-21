<?php

namespace App\Determinism\Types;

/**
 * The class Outer imports. A run that loses Outer's imports halfway through
 * resolves the name against Outer's own namespace instead and lands on
 * App\Determinism\Marker, which is why that class exists too.
 */
class Marker
{
    //
}
