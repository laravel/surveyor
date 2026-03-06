<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\DTOs\MoneyDTO;
use App\DTOs\PriceDTO;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Workbench\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => 'User: '.$this->name,
        );
    }

    /**
     * @return Attribute<int|null, never>
     */
    protected function ageInMonths(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => null,
        );
    }

    protected function withoutDocBlock(): Attribute
    {
        return Attribute::make(
            get: fn (): string => 'no doc',
        );
    }

    protected function moneyInCents(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) ($this->attributes['amount'] * 100),
        );
    }

    protected function money(): Attribute
    {
        return Attribute::make(
            get: fn () => new MoneyDTO(
                amount: (int) ($this->attributes['amount'] ?? 0),
                currency: $this->attributes['currency'] ?? 'USD',
            ),
        );
    }

    protected function withoutDocBlockClosure(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                return 'no doc closure';
            },
        );
    }

    protected function moneyClosure(): Attribute
    {
        return Attribute::make(
            get: function (): MoneyDTO {
                return new MoneyDTO(
                    amount: (int) ($this->attributes['amount'] ?? 0),
                    currency: $this->attributes['currency'] ?? 'USD',
                );
            },
        );
    }

    protected function moneyClosureUntyped(): Attribute
    {
        return Attribute::make(
            get: function () {
                return new MoneyDTO(
                    amount: (int) ($this->attributes['amount'] ?? 0),
                    currency: $this->attributes['currency'] ?? 'USD',
                );
            },
        );
    }

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => new PriceDTO(
                amount: (int) ($this->attributes['amount'] ?? 0),
                currency: $this->attributes['currency'] ?? 'USD',
            ),
        );
    }
}
