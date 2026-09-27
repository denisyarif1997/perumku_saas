<?php

namespace App\Livewire\Resident;

use App\Models\User;
use App\Support\AdminMenu;
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

    /**
     * Route admin pertama yang benar-benar boleh diakses user, atau null bila
     * user tidak punya akses area admin sama sekali.
     *
     * Gerbangnya memakai permission (access-admin) yang sama dengan middleware
     * di routes/web.php, bukan nama role. Role bisa diubah bebas lewat halaman
     * Role & Akses, sehingga memfilternya per role membuat tombol ini muncul
     * untuk user yang akhirnya kena 403 — atau hilang untuk user yang
     * sebenarnya boleh masuk.
     */
    protected function adminLandingRoute(User $user): ?string
    {
        if (! $user->hasPermission('access-admin')) {
            return null;
        }

        if ($user->hasPermission('view-dashboard')) {
            return 'admin.dashboard';
        }

        // Tanpa izin lihat dashboard, arahkan ke modul pertama yang boleh dibuka.
        // Yang dicek adalah permission AKSI (penjaga route), bukan permission menu
        // (filter sidebar), supaya role hasil editan manual tetap punya tujuan
        // yang benar-benar bisa dimuat.
        foreach (AdminMenu::sections() as $section) {
            foreach ($section['items'] as $item) {
                $roles = $item[6] ?? [];
                if ($roles !== [] && ! $user->hasRole(...$roles)) {
                    continue;
                }

                [$route, $icon, $label, $pattern, $menuSlugs, $actionSlugs] = $item;

                if ($user->hasPermission(...$actionSlugs)) {
                    return $route;
                }
            }
        }

        return null;
    }

    #[Layout('layouts.resident', ['title' => 'Profil'])]
    public function render()
    {
        $user = Auth::user()->loadMissing(['resident.houseResidents.house.block', 'role.permissions']);

        return view('livewire.resident.profile', [
            'user' => $user,
            'adminLanding' => $this->adminLandingRoute($user),
        ]);
    }
}
