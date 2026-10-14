<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    #[Layout('layouts.auth')]
    public function render()
    {
        return view('livewire.auth.login');
    }

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        // Rate-limit brute force per email+IP (Livewire memakai POST update).
        $throttleKey = Str::transliterate(Str::lower(trim($this->email)).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.$seconds.' detik.',
            ]);
        }

        $user = User::query()->where('email', strtolower(trim($this->email)))->first();

        // Pendaftar pending: izinkan masuk untuk melanjutkan onboarding.
        if ($user && $user->status === 'pending' && $user->password && password_verify($this->password, $user->password)) {
            RateLimiter::clear($throttleKey);
            Auth::login($user, $this->remember);
            session()->regenerate();
            $user->update(['last_login_at' => now()]);

            return $this->redirectRoute('onboarding', navigate: true);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'status' => 'active'], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', 'Email atau password salah, atau akun tidak aktif.');

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();
        $user = Auth::user();
        $user->update(['last_login_at' => now()]);

        if ($user->hasPermission('access-admin')) {
            return $this->redirectRoute('admin.dashboard', navigate: true);
        }

        if ($user->isResident()) {
            return $this->redirectRoute('resident.dashboard', navigate: true);
        }

        Auth::logout();
        session()->invalidate();

        $this->addError('email', 'Akun Anda belum memiliki hak akses. Hubungi administrator.');
    }
}
