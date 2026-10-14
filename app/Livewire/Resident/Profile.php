<?php

namespace App\Livewire\Resident;

use App\Support\AdminMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->phone = $user->phone ?? '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'current_password' => ['nullable', 'string', 'required_with:new_password'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required_with' => 'Password saat ini wajib diisi untuk mengganti password.',
            'new_password.min' => 'Password baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user = Auth::user();
        $user->update(['name' => $data['name'], 'phone' => $data['phone'] ?: null]);
        if (! empty($data['new_password'])) {
            if (! Hash::check($data['current_password'] ?? '', $user->password)) {
                $this->addError('current_password', 'Password saat ini salah.');

                return;
            }
            // Cast 'hashed' di model yang melakukan hashing — jangan Hash::make di sini.
            $user->update(['password' => $data['new_password']]);
            Auth::logoutOtherDevices($data['current_password']);
        }
        if ($user->resident) {
            $user->resident->update(['name' => $data['name'], 'phone' => $data['phone'] ?: null]);
        }

        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        session()->flash('success', 'Profil berhasil disimpan.');
    }

    #[Layout('layouts.resident', ['title' => 'Profil'])]
    public function render()
    {
        $user = Auth::user()->loadMissing(['resident.houseResidents.house.block', 'role.permissions']);

        return view('livewire.resident.profile', [
            'user' => $user,
            'adminLanding' => AdminMenu::landingRouteFor($user),
        ]);
    }
}
