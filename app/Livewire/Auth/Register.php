<?php

namespace App\Livewire\Auth;

use App\Models\HousingEstate;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentEstate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Onboarding tenant: mendaftarkan perumahan baru sekaligus akun adminnya.
 *
 * Proses ini dijalankan sebagai tamu, sehingga konteks estate masih kosong
 * (mode platform). Karena itu housing_estate_id ditulis eksplisit dan tidak
 * dikunci oleh hook tulis global scope.
 */
class Register extends Component
{
    public string $estateCode = '';

    public string $estateName = '';

    public string $name = '';

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
            'estateCode' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:housing_estates,code'],
            'estateName' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'estateCode.required' => 'Kode perumahan wajib diisi.',
            'estateCode.alpha_dash' => 'Kode perumahan hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'estateCode.unique' => 'Kode perumahan sudah dipakai.',
            'estateName.required' => 'Nama perumahan wajib diisi.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $adminRole = Role::where('slug', 'admin')->first();

        if (! $adminRole) {
            session()->flash('error', 'Role admin belum tersedia. Jalankan seeder role terlebih dahulu.');

            return;
        }

        $user = DB::transaction(function () use ($data, $adminRole): User {
            $estate = HousingEstate::create([
                'code' => strtoupper($data['estateCode']),
                'name' => $data['estateName'],
                'email' => $data['email'],
                'status' => 'active',
            ]);

            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id' => $adminRole->id,
                'housing_estate_id' => $estate->id,
                'status' => 'active',
            ]);
        });

        // Konteks estate dihitung ulang untuk user yang baru masuk.
        CurrentEstate::flush();

        Auth::login($user);
        session()->regenerate();
        $user->update(['last_login_at' => now()]);

        $this->reset(['estateCode', 'estateName', 'name', 'email', 'password', 'password_confirmation']);

        return $this->redirectRoute('admin.dashboard', navigate: true);
    }
}
