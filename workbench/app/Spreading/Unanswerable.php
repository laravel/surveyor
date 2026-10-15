<?php

namespace App\Spreading;

class Unanswerable
{
    public function rules(): array
    {
        return [
            'name' => ['required'],
            ...$this->fromElsewhere(),
        ];
    }

    /**
     * Nothing in the body says which keys come back, so the declared type is
     * all there is to go on however often the file is read.
     */
    protected function fromElsewhere(): array
    {
        return config('pricing.rules');
    }
}
