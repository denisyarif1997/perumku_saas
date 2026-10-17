<?php

namespace App\Services;

use App\Jobs\SendPanicPush;
use App\Models\EmergencyPanic;
use App\Models\Resident;
use App\Models\Scopes\BelongsToEstateScope;
use App\Models\User;
use App\Notifications\EmergencyPanicNotification;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmergencyPanicService
{
    public function fire(array $data): EmergencyPanic
    {
        $this->authorize();

        $user = Auth::user();
        $residentId = $this->resolveResidentId($data, $user);

        $this->ensureResidentBelongsToEstate($user, $residentId);

        $data = $this->validate($data);

        // Alarm aktif identik → kembalikan yang sama (idempoten), jangan dobel.
        $existing = $this->findActiveDuplicate($user->id, $residentId, $data);

        if ($existing !== null) {
            return $existing;
        }

        return $this->persistAndNotify($user, $residentId, $data);
    }

    public function markResolved(EmergencyPanic $panic, ?string $note = null): EmergencyPanic
    {
        $panic->update([
            'status' => 'resolved',
            'meta' => $note !== null ? array_merge($panic->meta ?? [], ['note' => $note]) : $panic->meta,
        ]);

        return $panic->fresh();
    }

    public function aliveOpenByUser(int $userId): Builder
    {
        return EmergencyPanic::query()->open()->where('user_id', $userId);
    }

    public function openByUser(int $userId): array
    {
        return $this->aliveOpenByUser($userId)
            ->with(['user', 'resident'])
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function countOpenByUser(int $userId): int
    {
        return $this->aliveOpenByUser($userId)->count();
    }

    public function getOpenById(int $id): EmergencyPanic
    {
        $panic = EmergencyPanic::query()->open()->find($id);

        if (! $panic) {
            throw new ModelNotFoundException('Active emergency panic not found.');
        }

        return $panic;
    }

    protected function authorize(): void
    {
        if (! Auth::check()) {
            abort(403, 'Anda harus login terlebih dahulu.');
        }

        $user = Auth::user();

        if (! $user->hasPermission('resident-panic')) {
            abort(403, 'Anda tidak memiliki izin mengakses fitur ini.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function validate(array $data): array
    {
        return Validator::make($data, [
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Keterangan darurat wajib diisi.',
        ])->validate();
    }

    protected function resolveResidentId(array $data, User $user): int
    {
        if (isset($data['resident_id'])) {
            return (int) $data['resident_id'];
        }

        if ($user->resident_id !== null) {
            return (int) $user->resident_id;
        }

        abort(403, 'Akun ini tidak terhubung ke data warga.');
    }

    protected function ensureResidentBelongsToEstate(User $user, int $residentId): void
    {
        $resident = Resident::find($residentId);

        if (! $resident) {
            abort(404, 'Warga tidak ditemukan.');
        }

        if (! $resident->houseResidents->contains(fn ($pivot) => $pivot->status === 'active')) {
            abort(403, 'Warga ini belum memiliki hunian aktif.');
        }

        $userEstateId = CurrentEstate::id();

        $residentEstateId = $resident->houseResidents
            ->where('status', 'active')
            ->first()
            ->house->housing_estate_id;

        if ($userEstateId !== null && $userEstateId !== CurrentEstate::NONE && (int) $residentEstateId !== $userEstateId) {
            abort(403, 'Warga ini tidak termasuk estate Anda.');
        }
    }

    protected function findActiveDuplicate(int $userId, int $residentId, array $data): ?EmergencyPanic
    {
        $query = EmergencyPanic::query()
            ->open()
            ->where('user_id', $userId)
            ->where('resident_id', $residentId);

        if (! empty($data['reason'])) {
            $query->where('reason', $data['reason']);
        }

        return $query->latest('id')->first();
    }

    protected function persistAndNotify(User $user, int $residentId, array $data): EmergencyPanic
    {
        $panic = EmergencyPanic::create([
            'user_id' => $user->id,
            'resident_id' => $residentId,
            'reason' => $data['reason'] ?? null,
            'latitude' => null,
            'longitude' => null,
            'status' => 'open',
            'reference_type' => null,
            'reference_id' => null,
            'meta' => [
                'source' => $data['source'] ?? 'resident_dashboard',
            ],
        ]);

        $estateId = $this->resolveEstateId($residentId);

        // Alarm darurat disiarkan ke satu perumahan yang sama:
        // staf keamanan/pengelola + sesama warga (pengirim dikecualikan).
        // Filter estate ditulis eksplisit per query (tanpa mengandalkan
        // global scope dari sesi pengirim) agar tidak bocor ke perumahan lain.
        $recipientIds = [];
        $this->recipientQuery($user->id, $estateId)
            ->orderBy('id')
            ->chunkById(200, function ($recipients) use ($panic, $user, &$recipientIds): void {
                foreach ($recipients as $recipient) {
                    $recipient->notify(new EmergencyPanicNotification($panic, $user->name));
                    $recipientIds[] = $recipient->id;
                }
            });

        // Web Push background (bahkan saat tab ditutup) — dikirim via antrean
        // agar tombol Kirim Alarm tetap cepat. Scope penerima sama persis.
        $recipientIds = array_values(array_unique($recipientIds));

        if ($recipientIds !== []) {
            SendPanicPush::dispatch($panic->id, $recipientIds);
        }

        return $panic;
    }

    /**
     * @return Builder<User>
     */
    protected function recipientQuery(int $senderId, int $estateId): Builder
    {
        return User::withoutGlobalScope(BelongsToEstateScope::class)
            ->where('status', 'active')
            ->where('id', '!=', $senderId)
            ->where(function (Builder $query) use ($estateId): void {
                $query
                    ->whereHas('role', fn (Builder $role) => $role->where('slug', 'super_admin'))
                    ->orWhereHas('role.permissions', fn (Builder $perm) => $perm->where('slug', 'manage-emergency'))
                    ->orWhereHas('resident.houseResidents', fn (Builder $pivot) => $pivot
                        ->where('status', 'active')
                        ->whereHas('house', fn (Builder $house) => $house->where('housing_estate_id', $estateId)));
            });
    }

    /**
     * Estate (perumahan) hunian aktif warga pengirim alarm.
     */
    protected function resolveEstateId(int $residentId): int
    {
        $estateId = DB::table('house_residents')
            ->join('houses', 'houses.id', '=', 'house_residents.house_id')
            ->where('house_residents.resident_id', $residentId)
            ->where('house_residents.status', 'active')
            ->orderByDesc('house_residents.is_primary')
            ->value('houses.housing_estate_id');

        abort_unless($estateId !== null, 403, 'Warga ini belum memiliki hunian aktif.');

        return (int) $estateId;
    }

    
/**
 * Riwayat emergency panic seluruh warga dalam estate aktif.
 */
public function historyByEstate(): Builder
{
    $estateId = CurrentEstate::id();

    abort_if(
        $estateId === null || $estateId === CurrentEstate::NONE,
        403,
        'Perumahan aktif tidak ditemukan.'
    );

    return EmergencyPanic::query()
        ->whereHas('resident.houseResidents', function (Builder $query) use ($estateId) {
            $query
                ->where('status', 'active')
                ->whereHas('house', function (Builder $house) use ($estateId) {
                    $house->where('housing_estate_id', $estateId);
                });
        })
        ->with(['user', 'resident'])
        ->orderByDesc('created_at');
}

}
