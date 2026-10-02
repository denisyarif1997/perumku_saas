<?php

namespace Database\Seeders;

use App\Models\House;
use App\Models\HouseResident;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\IplRate;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HousingSeeder extends Seeder
{
    public function run(): void
    {
        $estate = HousingEstate::firstOrCreate(['code' => 'HH-01'], [
            'name' => 'HousingHub Residence',
            'address' => 'Jl. Contoh No. 1, Jakarta',
            'phone' => '021-1234567',
            'email' => 'info@housinghub.id',
            'status' => 'active',
        ]);

        foreach (['A', 'B', 'C'] as $code) {
            HousingBlock::firstOrCreate(
                ['housing_estate_id' => $estate->id, 'code' => $code],
                ['name' => "Blok $code", 'description' => "Blok $code HousingHub", 'status' => 'active']
            );
        }

        $blocks = HousingBlock::where('housing_estate_id', $estate->id)->get();
        $residentRole = Role::where('slug', 'resident')->first();

        IplRate::firstOrCreate(
            ['housing_estate_id' => $estate->id, 'name' => 'Iuran Warga'],
            [
                'amount' => 150000,
                'period_type' => 'monthly',
                'effective_date' => now()->startOfYear()->toDateString(),
                'description' => 'Tarif iuran bulanan standar warga',
                'status' => 'active',
            ]
        );

        $counter = 1;
        foreach ($blocks as $block) {
            for ($i = 1; $i <= 4; $i++) {
                $house = House::firstOrCreate(
                    ['housing_block_id' => $block->id, 'house_number' => (string) $i],
                    [
                        'housing_estate_id' => $estate->id,
                        'address' => "Blok {$block->code} No. $i",
                        'land_area' => 90,
                        'building_area' => 60,
                        'ownership_status' => 'owner',
                        'occupancy_status' => 'occupied',
                        'status' => 'active',
                    ]
                );

                $resident = Resident::firstOrCreate(['nik' => '3174000000000'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT)], [
                    'name' => "Warga {$block->code}-$i",
                    'gender' => $i % 2 === 0 ? 'female' : 'male',
                    'phone' => '081200000'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT),
                    'email' => strtolower("warga.{$block->code}.$i@housinghub.id"),
                    'status' => 'active',
                ]);

                HouseResident::firstOrCreate(
                    ['house_id' => $house->id, 'resident_id' => $resident->id],
                    [
                        'relationship' => 'owner',
                        'is_owner' => true,
                        'is_primary' => true,
                        'start_date' => now()->subYear()->toDateString(),
                        'status' => 'active',
                    ]
                );

                User::firstOrCreate(['email' => $resident->email], [
                    'name' => $resident->name,
                    'password' => Hash::make('password123'),
                    'resident_id' => $resident->id,
                    'housing_estate_id' => $estate->id,
                    'role_id' => $residentRole?->id,
                    'phone' => $resident->phone,
                    'status' => 'active',
                ]);

                $counter++;
            }
        }
    }
}
