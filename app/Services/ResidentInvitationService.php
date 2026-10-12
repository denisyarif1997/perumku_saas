<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\Resident;
use App\Models\ResidentInvitation;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Support\CurrentEstate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/** Buat undangan warga tanpa SMTP: link bertoken + share WhatsApp. */
class ResidentInvitationService
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    /**
     * @return array{invitation: ResidentInvitation, token: string, url: string}
     */
    public function create(User $inviter, array $data): array
    {
        $estateId = CurrentEstate::id();

        if ($estateId === null || $estateId === CurrentEstate::NONE) {
            throw new RuntimeException('Akun Anda belum terikat ke perumahan mana pun.');
        }

        $house = House::findOrFail((int) $data['house_id']);

        if ((int) $house->housing_estate_id !== (int) $estateId) {
            throw new RuntimeException('Rumah bukan bagian dari perumahan Anda.');
        }

        $this->subscriptions->assertCanAddUser($estateId);

        $email = isset($data['email']) && $data['email'] !== '' ? strtolower(trim((string) $data['email'])) : null;

        if ($email && User::query()->where('email', $email)->exists()) {
            throw new RuntimeException('Email sudah punya akun. Minta warga masuk langsung.');
        }

        $token = Str::random(48);
        $residentId = isset($data['resident_id']) && $data['resident_id'] !== '' ? (int) $data['resident_id'] : null;

        $invitation = DB::transaction(fn () => ResidentInvitation::create([
            'housing_estate_id' => $estateId,
            'resident_id' => $residentId,
            'house_id' => $house->id,
            'invited_by' => $inviter->id,
            'name' => trim((string) $data['name']),
            'phone' => isset($data['phone']) && $data['phone'] !== '' ? trim((string) $data['phone']) : null,
            'email' => $email,
            'token_hash' => Hash::make($token),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]));

        ActivityLog::record([
            'user_id' => $inviter->id,
            'action' => 'create',
            'module' => 'residents',
            'subject_type' => ResidentInvitation::class,
            'subject_id' => $invitation->id,
            'description' => 'Membuat undangan warga '.$invitation->name,
        ]);

        return [
            'invitation' => $invitation,
            'token' => $token,
            'url' => route('invite.show', ['token' => $token]),
        ];
    }

    /** Tebus undangan: buat Resident (bila baru) + akun User resident aktif. */
    public function accept(ResidentInvitation $invitation, array $data): User
    {
        if (! $invitation->isPending()) {
            throw new RuntimeException('Undangan tidak berlaku / kedaluwarsa.');
        }

        $email = strtolower(trim((string) ($data['email'] ?? $invitation->email ?? '')));

        if ($email === '') {
            throw new RuntimeException('Email wajib diisi.');
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new RuntimeException('Email sudah punya akun. Silakan masuk langsung.');
        }

        $this->subscriptions->assertCanAddUser((int) $invitation->housing_estate_id);

        return DB::transaction(function () use ($invitation, $data, $email) {
            $resident = $invitation->resident;

            if (! $resident) {
                $resident = Resident::create([
                    'name' => trim((string) ($data['name'] ?? $invitation->name)),
                    'phone' => isset($data['phone']) && $data['phone'] !== '' ? trim((string) $data['phone']) : $invitation->phone,
                    'email' => $email,
                    'status' => 'active',
                ]);

                if ($invitation->house_id) {
                    HouseResident::updateOrCreate(
                        ['house_id' => $invitation->house_id, 'resident_id' => $resident->id],
                        ['relationship' => 'owner', 'is_owner' => false, 'is_primary' => true, 'start_date' => now()->toDateString(), 'status' => 'active']
                    );
                }
            } else {
                $resident->forceFill(['email' => $email, 'status' => 'active'])->save();
            }

            $user = User::create([
                'name' => $resident->name,
                'email' => $email,
                'password' => $data['password'],
                'phone' => $resident->phone,
                'role_id' => Role::query()->where('slug', 'resident')->value('id'),
                'resident_id' => $resident->id,
                'housing_estate_id' => $invitation->housing_estate_id,
                'status' => 'active',
            ]);

            $invitation->forceFill([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
                'resident_id' => $resident->id,
            ])->save();

            return $user;
        });
    }

    public function revoke(ResidentInvitation $invitation, User $actor): void
    {
        if ($invitation->status !== 'pending') {
            throw new RuntimeException('Hanya undangan pending yang bisa dibatalkan.');
        }

        $invitation->forceFill(['status' => 'revoked'])->save();

        ActivityLog::record([
            'user_id' => $actor->id,
            'action' => 'delete',
            'module' => 'residents',
            'subject_type' => ResidentInvitation::class,
            'subject_id' => $invitation->id,
            'description' => 'Membatalkan undangan warga '.$invitation->name,
        ]);
    }

    public function waLink(ResidentInvitation $invitation, string $url): string
    {
        $estate = $invitation->estate?->name ?? 'perumahan';
        $text = "Halo {$invitation->name}, Anda diundang bergabung ke aplikasi {$estate}.\n".
            "Klik link ini untuk membuat akun (berlaku 7 hari):\n{$url}";

        return 'https://wa.me/?text='.rawurlencode($text);
    }
}
