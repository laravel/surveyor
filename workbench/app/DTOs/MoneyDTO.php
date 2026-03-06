<?php

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;

class MoneyDTO implements Arrayable
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {
        //
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'currency_amount' => $this->currency.' '.$this->amount,
        ];
    }
}
