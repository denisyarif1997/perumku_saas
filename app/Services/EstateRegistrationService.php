<?php

namespace App\Services;

use App\Models\EstateRegistration;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\OAuthAccount;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyetujui pengajuan perumahan baru dan meng-inject datanya ke tabel
 * produksi dalam satu transaksi: estate → blok + rumah pertama (opsional)
 * → resident → user dijadikan admin + diikat estate + resident.
 */
class EstateRegistrationService
{
    /**
     * @return array{estate: HousingEstate, user: User}
     */
    public function approve(EstateRegistration $registration, User $decider): array
    {
        if (! $registration->isPending()) {
            throw new RuntimeException('Pengajuan sudah diputuskan.');
        }

        return DB::transaction(function () use ($registration, $decider) {
            $user = $registration->user;

            if (! $user) {
                $user = User::query()->where('email', $registration->email)->first();
            }

            if (! $user) {
                throw new RuntimeException('Akun pemohon tidak ditemukan.');
            }

            if ($registration->user_id && (int) $registration->user_id !== (int) $user->id) {
                throw new RuntimeException('Akun pemohon berubah, tolak pengajuan ini dan minta daftar ulang.');
            }

            $estateCode = strtoupper(trim($registration->estate_code));

            if (HousingEstate::query()->where('code', $estateCode)->exists()) {
                throw new RuntimeException('Kode perumahan sudah dipakai.');
            }

            $estate = HousingEstate::create([
                'code' => $estateCode,
                'name' => $registration->estate_name,
                'address' => $registration->estate_address,
                'phone' => $registration->estate_phone ?? $registration->phone,
                'email' => $registration->estate_email ?? $registration->email,
                'status' => 'active',
            ]);

            $resident = null;
            $house = null;

            if ($registration->hasHouseData()) {
                $block = HousingBlock::create([
                    'housing_estate_id' => $estate->id,
                    'code' => strtoupper(trim((string) $registration->block_code)),
                    'name' => $registration->block_name ?: 'Blok '.strtoupper(trim((string) $registration->block_code)),
                    'status' => 'active',
                ]);

                $house = House::create([
                    'housing_estate_id' => $estate->id,
                    'housing_block_id' => $block->id,
                    'house_number' => trim((string) $registration->house_number),
                    'ownership_status' => 'owner',
                    'occupancy_status' => 'occupied',
                    'status' => 'active',
                ]);

                $resident = Resident::create([
                    'nik' => $registration->nik,
                    'name' => $registration->name,
                    'gender' => $registration->gender,
                    'birth_date' => $registration->birth_date?->toDateString(),
                    'phone' => $registration->phone,
                    'email' => $registration->email,
                    'status' => 'active',
                ]);

                HouseResident::create([
                    'house_id' => $house->id,
                    'resident_id' => $resident->id,
                    'relationship' => 'owner',
                    'is_owner' => true,
                    'is_primary' => true,
                    'start_date' => now()->toDateString(),
                    'status' => 'active',
                ]);
            } else {
                $resident = Resident::create([
                    'nik' => $registration->nik,
                    'name' => $registration->name,
                    'gender' => $registration->gender,
                    'birth_date' => $registration->birth_date?->toDateString(),
                    'phone' => $registration->phone,
                    'email' => $registration->email,
                    'status' => 'active',
                ]);
            }

            $adminRoleId = Role::query()->where('slug', 'admin')->value('id');

            $user->forceFill([
                'name' => $registration->name,
                'phone' => $registration->phone,
                'role_id' => $adminRoleId ?? $user->role_id,
                'housing_estate_id' => $estate->id,
                'resident_id' => $resident->id,
                'status' => 'active',
            ])->save();

            if ($registration->bridge_id) {
                OAuthAccount::query()
                    ->whereKey($registration->bridge_id)
                    ->update(['user_id' => $user->id, 'last_login_at' => now()]);
            }

            $registration->forceFill([
                'user_id' => $user->id,
                'status' => 'approved',
                'reject_reason' => null,
                'decided_by' => $decider->id,
                'decided_at' => now(),
            ])->save();

            return ['estate' => $estate, 'user' => $user->fresh()];
        });
    }

    public function reject(EstateRegistration $registration, User $decider, ?string $reason = null): void
    {
        if (! $registration->isPending()) {
            throw new RuntimeException('Pengajuan sudah diputuskan.');
        }

        $registration->forceFill([
            'status' => 'rejected',
            'reject_reason' => $reason,
            'decided_by' => $decider->id,
            'decided_at' => now(),
        ])->save();
    }
}
