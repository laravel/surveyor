<?php

namespace App\Http\Requests\Concerns;

trait HasPriceRules
{
    protected function priceRules(): array
    {
        return [
            'price_amount' => ['required', 'numeric', 'min:0'],
            'price_currency' => ['required', 'string'],
        ];
    }
}
