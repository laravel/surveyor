<?php

namespace App\DTOs;

use JsonSerializable;

class PriceDTO implements JsonSerializable
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {
        //
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'currency_amount' => $this->currency.' '.$this->amount,
        ];
    }
}
