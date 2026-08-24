<?php

namespace App\Models;

use App\Support\Phone;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'surname',
    'first_name',
    'other_name',
    'phone',
    'email',
    'password',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    // the last gate before storage, so a number reaching the column from any direction has one spelling
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn(?string $value) => Phone::normalise($value) ?? $value,
        );
    }

    protected function casts(): array
    {
        return [
            'phone_verified_at'            => 'datetime',
            'email_verified_at'            => 'datetime',
            'password'                     => 'hashed',
            'is_active'                    => 'boolean',
            'logins_since_verification'    => 'integer',
            'verification_login_threshold' => 'integer',
            // turns the stored text into a date object
            'next_verification_at'         => 'datetime',
        ];
    }
}
