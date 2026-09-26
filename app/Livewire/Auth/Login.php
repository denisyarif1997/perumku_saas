<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
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

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'status' => 'active'], $this->remember)) {
            $this->addError('email', 'Email atau password salah, atau akun tidak aktif.');

            return;
        }

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
