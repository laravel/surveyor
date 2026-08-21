<?php

namespace App\Determinism\Cycle;

class Alpha
{
    public function label(): string
    {
        return 'alpha';
    }

    /**
     * Nothing here says what this returns. Answering means knowing what
     * `static` means inside Beta, which is only knowable once Beta's own
     * analysis has finished.
     */
    public function beta()
    {
        return Beta::make();
    }
}
