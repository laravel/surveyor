<?php

namespace Laravel\Surveyor\Analyzed;

use Laravel\Surveyor\Support\Markers;

final class IgnoreMarker
{
    /**
     * Declared rather than promoted so that unserializing a marker written by
     * an older version of this class falls back to the defaults instead of
     * leaving a typed property uninitialized.
     */
    public string|array|null $unless = null;

    public string|array|null $when = null;

    public function __construct(
        string|array|null $unless = null,
        string|array|null $when = null,
    ) {
        $this->unless = self::condition($unless);
        $this->when = self::condition($when);
    }

    /**
     * Whether the marker hides its declaration right now.
     *
     * A marker with no condition always hides. Otherwise each condition can
     * only add hiding, never take it away, so combining the two is safe and
     * anything that is not a condition at all leaves nothing behind: it lands
     * here as no condition, which hides. Over-hiding shows up as a type error,
     * while under-hiding ships the thing the marker was put there to hold back.
     */
    public function hides(): bool
    {
        if ($this->unless === null && $this->when === null) {
            return true;
        }

        // A condition nothing can answer is not a condition that failed. With
        // no resolver registered there is no way to tell whether it holds, so
        // this lands with the unreadable ones and hides.
        if (! Markers::canResolveConditions()) {
            return true;
        }

        if ($this->when !== null && Markers::conditionPasses($this->when)) {
            return true;
        }

        return $this->unless !== null && ! Markers::conditionPasses($this->unless);
    }

    /**
     * A condition is a config key or a [class, method] callable. Anything else
     * is not a condition, whatever it was written as.
     */
    protected static function condition(string|array|null $condition): string|array|null
    {
        if (is_string($condition)) {
            return $condition === '' ? null : $condition;
        }

        if (! is_array($condition) || count($condition) !== 2 || ! array_is_list($condition)) {
            return null;
        }

        [$class, $method] = $condition;

        return is_string($class) && is_string($method) ? $condition : null;
    }
}
