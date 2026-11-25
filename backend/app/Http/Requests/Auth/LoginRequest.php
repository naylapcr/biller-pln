<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Batas percobaan login per menit per username dan IP. */
    private const MAX_PERCOBAAN_PER_MENIT = 5;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'login' => 'NIP/username',
            'password' => 'kata sandi',
        ];
    }

    /**
     * Login dengan username_nip (PRD 8). Respons gagal menyertakan sisa percobaan (contoh 3/5).
     * Setelah 5 kali gagal berturut-turut akun terkunci sampai dibuka Super Admin, walaupun
     * kata sandi berikutnya benar. Tetap dibatasi 5 percobaan per menit per username dan IP.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $kredensial = [
            'username_nip' => trim((string) $this->input('login')),
            'password' => (string) $this->input('password'),
        ];
        /** @var User|null $user */
        $user = Auth::getProvider()->retrieveByCredentials(['username_nip' => $kredensial['username_nip']]);

        if ($user?->terkunci()) {
            throw ValidationException::withMessages(['login' => self::pesanTerkunci()]);
        }
        $this->ensureIsNotRateLimited();

        if (! $user || ! Auth::getProvider()->validateCredentials($user, $kredensial)) {
            RateLimiter::hit($this->throttleKey(), 60);
            $pesan = 'NIP atau kata sandi tidak cocok.';

            if ($user) {
                $gagal = $user->gagal_login + 1;
                $terkunci = $gagal >= User::MAKS_GAGAL_LOGIN;
                $user->forceFill(['gagal_login' => $gagal, 'terkunci_at' => $terkunci ? now() : null])->saveQuietly();
                if ($terkunci) {
                    Audit::catat(Audit::LOCK_USER, $user, null, ['gagal_login' => $gagal], $user->id);

                    throw ValidationException::withMessages(['login' => self::pesanTerkunci()]);
                }
                $pesan .= ' Sisa percobaan: '.(User::MAKS_GAGAL_LOGIN - $gagal).'/'.User::MAKS_GAGAL_LOGIN;
            }

            throw ValidationException::withMessages(['login' => $pesan]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.']);
        }

        $user->forceFill(['gagal_login' => 0])->saveQuietly();
        Auth::login($user);
        RateLimiter::clear($this->throttleKey());
    }

    public static function pesanTerkunci(): string
    {
        return 'Akun terkunci setelah '.User::MAKS_GAGAL_LOGIN.' kali gagal masuk. Hubungi Super Admin untuk membukanya.';
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_PERCOBAAN_PER_MENIT)) {
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
