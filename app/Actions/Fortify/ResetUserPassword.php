<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'This account is inactive. Contact your administrator.']);
        }
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
            'requires_password_setup' => false,
            'email_verified_at' => $user->requires_password_setup ? now() : $user->email_verified_at,
            'remember_token' => Str::random(60),
        ])->save();
    }
}
