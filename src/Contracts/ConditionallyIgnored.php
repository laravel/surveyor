<?php

namespace Laravel\Surveyor\Contracts;

/**
 * An ignore marker that only applies some of the time.
 *
 * Conditions are resolved when the marked declaration is read, not while the
 * file is analyzed, so a cached analysis holds the condition rather than the
 * answer to it. Each condition is a config key or a [class, method] callable,
 * or null for "not set".
 */
interface ConditionallyIgnored extends Ignored
{
    /**
     * Keep the declaration while this passes.
     */
    public function unless(): string|array|null;

    /**
     * Leave the declaration out while this passes.
     */
    public function when(): string|array|null;
}
