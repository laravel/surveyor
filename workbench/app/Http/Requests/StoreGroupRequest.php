<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasPriceRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    use HasPriceRules;

    /**
     * @return array<string, mixed>
     */
    protected function noteRules(): array
    {
        return [
            'note' => ['nullable', 'string'],
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            ...$this->priceRules(),
            ...$this->noteRules(),
            ...$this->contactRules(),
        ];
    }

    protected function contactRules(): array
    {
        return [
            'email' => ['required', 'email'],
            ...$this->addressRules(),
        ];
    }

    protected function addressRules(): array
    {
        return [
            'city' => ['required', 'string'],
        ];
    }

    public function merged(): array
    {
        return array_merge($this->priceRules(), [
            'name' => ['required', 'string', 'max:255'],
        ]);
    }

    public function onlySpreads(): array
    {
        return [...$this->priceRules(), ...$this->noteRules()];
    }
}
