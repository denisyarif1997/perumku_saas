<?php

namespace App\Livewire\Admin\Residents;

use App\Models\ActivityLog;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    public ?Resident $resident = null;

    public string $name = '';

    public string $nik = '';

    public string $gender = 'male';

    public string $birth_date = '';

    public string $phone = '';

    public string $email = '';

    public string $house_id = '';

    public string $relationship = 'owner';

    public bool $create_account = true;

    public function mount(?Resident $resident = null): void
    {
        $this->authorize($resident?->exists ? 'update' : 'create', $resident?->exists ? $resident : Resident::class);
        if ($resident?->exists) {
            $this->resident = $resident->load('houseResidents');
            $this->name = $resident->name;
            $this->nik = $resident->nik ?? '';
            $this->gender = $resident->gender ?? 'male';
            $this->birth_date = $resident->birth_date?->format('Y-m-d') ?? '';
            $this->phone = $resident->phone ?? '';
            $this->email = $resident->email ?? '';
            $pivot = $resident->houseResidents->firstWhere('is_primary', true) ?? $resident->houseResidents->first();
            $this->house_id = $pivot ? (string) $pivot->house_id : '';
            $this->relationship = $pivot?->relationship ?? 'owner';
            $this->create_account = ! User::where('resident_id', $resident->id)->exists() ? false : false;
        }
    }

    public function save()
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'nik' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'house_id' => ['required', 'exists:houses,id'],
            'relationship' => ['required', 'string', 'max:30'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'house_id.required' => 'Rumah wajib dipilih.',
        ]);

        DB::transaction(function () use ($data) {
            if ($this->resident) {
                $this->resident->update([
                    'name' => $data['name'], 'nik' => $data['nik'] ?: null, 'gender' => $data['gender'],
                    'birth_date' => $data['birth_date'] ?: null, 'phone' => $data['phone'] ?: null,
                    'email' => $data['email'] ?: null,
                ]);
                $resident = $this->resident;
                ActivityLog::record([
                    'user_id' => auth()->id(), 'action' => 'update', 'module' => 'residents',
                    'subject_type' => Resident::class, 'subject_id' => $resident->id,
                    'description' => 'Mengubah warga '.$resident->name,
                ]);
            } else {
                $resident = Resident::create([
                    'name' => $data['name'], 'nik' => $data['nik'] ?: null, 'gender' => $data['gender'],
                    'birth_date' => $data['birth_date'] ?: null, 'phone' => $data['phone'] ?: null,
                    'email' => $data['email'] ?: null, 'status' => 'active',
                ]);
                ActivityLog::record([
                    'user_id' => auth()->id(), 'action' => 'create', 'module' => 'residents',
                    'subject_type' => Resident::class, 'subject_id' => $resident->id,
                    'description' => 'Menambah warga '.$resident->name,
                ]);
            }

            HouseResident::where('resident_id', $resident->id)->where('status', 'active')->update(['is_primary' => false]);
            HouseResident::updateOrCreate(
                ['house_id' => $data['house_id'], 'resident_id' => $resident->id],
                ['relationship' => $data['relationship'], 'is_owner' => $data['relationship'] === 'owner', 'is_primary' => true, 'start_date' => now()->toDateString(), 'status' => 'active']
            );

            if (! $this->resident && $this->create_account && $data['email']) {
                $role = Role::where('slug', 'resident')->first();
                User::firstOrCreate(['email' => $data['email']], [
                    'name' => $data['name'], 'password' => Hash::make('password123'),
                    'resident_id' => $resident->id, 'role_id' => $role?->id,
                    'phone' => $data['phone'] ?: null, 'status' => 'active',
                ]);
            }
        });

        session()->flash('success', 'Data warga berhasil disimpan.');

        return $this->redirectRoute('admin.residents.index', navigate: true);
    }

    #[Layout('layouts.admin', ['title' => 'Form Warga'])]
    public function render()
    {
        return view('livewire.admin.residents.form', [
            'houses' => House::with('block')->orderBy('house_number')->get(),
        ]);
    }
}
