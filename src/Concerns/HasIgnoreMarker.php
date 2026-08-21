<?php

namespace Laravel\Surveyor\Concerns;

use Laravel\Surveyor\Analyzed\IgnoreMarker;

trait HasIgnoreMarker
{
    /**
     * Declared rather than promoted by the classes using this, so that
     * unserializing a result written before the marker existed falls back to
     * the default instead of leaving a typed property uninitialized.
     */
    protected ?IgnoreMarker $ignore = null;

    public function flagAsIgnored(?IgnoreMarker $marker = null): void
    {
        $this->ignore = $marker ?? new IgnoreMarker;
    }

    public function ignoreMarker(): ?IgnoreMarker
    {
        return $this->ignore;
    }

    /**
     * Whether this should be left out as things stand. A marker carrying a
     * condition is answered here, when it is read, rather than when the file it
     * came from was analyzed.
     */
    public function isIgnored(): bool
    {
        return $this->ignore?->hides() ?? false;
    }
}
