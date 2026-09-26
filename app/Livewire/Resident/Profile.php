<?php

namespace App\Livewire\Resident;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $new_password = '';

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
            'new_password' => ['nullable', 'string', 'min:8'],
        ]);

        $user = Auth::user();
        $user->update(['name' => $data['name'], 'phone' => $data['phone'] ?: null]);
        if (! empty($data['new_password'])) {
            $user->update(['password' => Hash::make($data['new_password'])]);
        }
        if ($user->resident) {
            $user->resident->update(['name' => $data['name'], 'phone' => $data['phone'] ?: null]);
        }

        $this->reset('new_password');
        session()->flash('success', 'Profil berhasil disimpan.');
    }

    #[Layout('layouts.resident', ['title' => 'Profil'])]
    public function render()
    {
        $user = Auth::user()->loadMissing(['resident.houseResidents.house.block', 'role']);

        return view('livewire.resident.profile', compact('user'));
    }
}
