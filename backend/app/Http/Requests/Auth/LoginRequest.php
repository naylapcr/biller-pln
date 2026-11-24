<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Batas percobaan login per menit (PRD: 5/menit). */
    private const MAX_PERCOBAAN = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'login' => 'username atau NIP',
            'password' => 'kata sandi',
        ];
    }

    /**
     * Login memakai username_nip. Akun nonaktif ditolak. Dibatasi 5 percobaan per menit per username dan IP.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $kredensial = [
            'username_nip' => trim((string) $this->input('login')),
            'password' => (string) $this->input('password'),
        ];

        if (! Auth::validate($kredensial)) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'login' => 'Username/NIP atau kata sandi tidak sesuai.',
            ]);
        }

        $user = Auth::getProvider()->retrieveByCredentials($kredensial);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_PERCOBAAN)) {
            return;
        }

        event(new Lockout($this));

        $detik = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('login')).'|'.$this->ip());
    }
}
