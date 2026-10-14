<?php

namespace App\Livewire\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Register extends Component
{
    public string $email = '';

    public string $password = '';

    /** Snake_case agar aturan validasi "confirmed" mencarinya dengan benar. */
    public string $password_confirmation = '';

    #[Layout('layouts.auth')]
    public function render()
    {
        return view('livewire.auth.register');
    }

    public function register()
    {
        $data = $this->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $email = strtolower(trim($data['email']));
        $existing = User::query()->where('email', $email)->first();

        // Keamanan: jangan auto-login akun pending hanya karena email cocok.
        // Siapa pun yang tahu email pending korban bisa mengambil alih akun.
        // Pemilik sah tetap bisa melanjutkan lewat login normal (password/OTP).
        if ($existing) {
            if ($existing->status === 'pending') {
                $this->addError('email', 'Email ini sudah mengajukan pendaftaran. Silakan masuk untuk melanjutkan.');
            } else {
                $this->addError('email', 'Email sudah terdaftar. Silakan masuk.');
            }

            return;
        }

        $user = User::create([
            'name' => explode('@', $email)[0],
            'email' => $email,
            'password' => $data['password'],
            'role_id' => Role::query()->where('slug', 'admin')->value('id'),
            'status' => 'pending',
        ]);

        Auth::login($user);
        session()->regenerate();

        return $this->redirectRoute('onboarding', navigate: true);
    }
}
