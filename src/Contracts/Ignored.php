<?php

namespace Laravel\Surveyor\Contracts;

/**
 * Marks an attribute class as meaning "leave this out of generated output".
 *
 * Consumers ship their own attribute implementing this, so users write a name
 * from the package they installed while surveyor stays free of any one
 * consumer's vocabulary.
 */
interface Ignored
{
    //
}
