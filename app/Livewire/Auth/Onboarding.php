<?php

namespace App\Livewire\Auth;

use App\Models\EstateRegistration;
use App\Models\OAuthAccount;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Satu langkah pendaftaran perumahan baru (email maupun Google).
 *
 * Email: buat akun pending + simpan staging, langsung login agar bisa
 * melengkapi/melacak status.
 * Google: akun pending + bridge dibuat di GoogleAuthController, halaman ini
 * tinggal melengkapi data diri + perumahan.
 */
class Onboarding extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $nik = '';

    public string $gender = '';

    public string $birth_date = '';

    public string $estateCode = '';

    public string $estateName = '';

    public string $estateAddress = '';

    public string $estatePhone = '';

    public string $estateEmail = '';

    public string $blockCode = '';

    public string $blockName = '';

    public string $houseNumber = '';

    public string $method = 'email';

    public ?int $registrationId = null;

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->redirectRoute('register', navigate: true);

            return;
        }

        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) ($user->phone ?? '');

        $registration = EstateRegistration::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($registration) {
            $this->registrationId = $registration->id;
            $this->method = $registration->method;
            $this->name = (string) $registration->name;
            $this->email = (string) $registration->email;
            $this->phone = (string) $registration->phone;
            $this->nik = (string) ($registration->nik ?? '');
            $this->gender = (string) ($registration->gender ?? '');
            $this->birth_date = $registration->birth_date?->toDateString() ?? '';
            $this->estateCode = (string) $registration->estate_code;
            $this->estateName = (string) $registration->estate_name;
            $this->estateAddress = (string) ($registration->estate_address ?? '');
            $this->estatePhone = (string) ($registration->estate_phone ?? '');
            $this->estateEmail = (string) ($registration->estate_email ?? '');
            $this->blockCode = (string) ($registration->block_code ?? '');
            $this->blockName = (string) ($registration->block_name ?? '');
            $this->houseNumber = (string) ($registration->house_number ?? '');
        } elseif ($user->socialAccounts()->where('provider', 'google')->exists()) {
            $this->method = 'google';
        }

        // Sudah aktif + terikat estate → bukan calon pendaftar lagi.
        if ($user->status === 'active' && $user->housing_estate_id) {
            $this->redirectRoute('admin.dashboard', navigate: true);
        }
    }

    public function save()
    {
        $user = Auth::user();

        if (! $user) {
            return $this->redirectRoute('register', navigate: true);
        }

        $isEmailMethod = $this->registrationId
            ? $this->method === 'email'
            : ! $user->socialAccounts()->where('provider', 'google')->exists();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'password' => $isEmailMethod && ! $this->registrationId && ! $user->password ? ['required', 'string', 'min:8', 'confirmed'] : ['nullable', 'string', 'min:8'],
            'nik' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'estateCode' => ['required', 'string', 'max:20', 'alpha_dash'],
            'estateName' => ['required', 'string', 'max:100'],
            'estateAddress' => ['nullable', 'string', 'max:500'],
            'estatePhone' => ['nullable', 'string', 'max:15'],
            'estateEmail' => ['nullable', 'email', 'max:150'],
            'blockCode' => ['nullable', 'string', 'max:20', 'alpha_dash', 'required_with:houseNumber'],
            'blockName' => ['nullable', 'string', 'max:100'],
            'houseNumber' => ['nullable', 'string', 'max:50', 'required_with:blockCode'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'phone.required' => 'Nomor HP/WA wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'estateCode.required' => 'Kode perumahan wajib diisi.',
            'estateCode.alpha_dash' => 'Kode hanya boleh huruf, angka, tanda hubung, garis bawah.',
            'estateName.required' => 'Nama perumahan wajib diisi.',
            'blockCode.required_with' => 'Kode blok wajib diisi bila nomor rumah diisi.',
            'houseNumber.required_with' => 'Nomor rumah wajib diisi bila kode blok diisi.',
        ]);

        $email = strtolower(trim($data['email']));
        $estateCode = strtoupper(trim($data['estateCode']));

        if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
            $this->addError('email', 'Email sudah dipakai akun lain.');

            return;
        }

        if (EstateRegistration::query()->where('email', $email)->where('status', 'pending')->where('user_id', '!=', $user->id)->exists()) {
            $this->addError('email', 'Email ini sudah mengajukan perumahan dan menunggu verifikasi.');

            return;
        }

        DB::transaction(function () use ($user, $data, $email, $estateCode, $isEmailMethod) {
            if ($isEmailMethod && ! empty($data['password'])) {
                $user->forceFill(['password' => $data['password']])->save();
            }

            $oauthAccountId = OAuthAccount::query()
                ->where('provider', 'google')
                ->where('email', $user->email)
                ->whereNull('user_id')
                ->value('id');

            $payload = [
                'method' => $isEmailMethod ? 'email' : 'google',
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => trim($data['phone']),
                'nik' => $data['nik'] ?: null,
                'gender' => $data['gender'] ?: null,
                'birth_date' => $data['birth_date'] ?: null,
                'estate_code' => $estateCode,
                'estate_name' => trim($data['estateName']),
                'estate_address' => $data['estateAddress'] ?: null,
                'estate_phone' => $data['estatePhone'] ?: null,
                'estate_email' => isset($data['estateEmail']) && $data['estateEmail'] ? strtolower(trim($data['estateEmail'])) : null,
                'block_code' => isset($data['blockCode']) && $data['blockCode'] ? strtoupper(trim($data['blockCode'])) : null,
                'block_name' => $data['blockName'] ?: null,
                'house_number' => isset($data['houseNumber']) && $data['houseNumber'] ? trim($data['houseNumber']) : null,
                'status' => 'pending',
            ];

            if ($this->registrationId) {
                EstateRegistration::query()
                    ->whereKey($this->registrationId)
                    ->where('user_id', $user->id)
                    ->update($payload);
            } else {
                $registration = EstateRegistration::create($payload + [
                    'user_id' => $user->id,
                    'bridge_id' => $oauthAccountId,
                ]);
                $this->registrationId = $registration->id;
                $this->method = $payload['method'];

                if ($oauthAccountId) {
                    OAuthAccount::query()->whereKey($oauthAccountId)->update(['user_id' => $user->id]);
                }
            }

            $user->forceFill([
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => trim($data['phone']),
                'role_id' => Role::query()->where('slug', 'admin')->value('id') ?? $user->role_id,
                'status' => 'pending',
            ])->save();
        });

        session()->flash('status', 'Pengajuan perumahan tersimpan. Tunggu verifikasi superadmin.');

        return $this->redirectRoute('onboarding', navigate: true);
    }

    #[Layout('layouts.auth')]
    public function render()
    {
        return view('livewire.auth.onboarding');
    }
}
