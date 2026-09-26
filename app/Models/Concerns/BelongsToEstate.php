<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BelongsToEstateScope;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToEstate
{
    public static function bootBelongsToEstate(): void
    {
        static::addGlobalScope(new BelongsToEstateScope);

        // Jaga sisi tulis: pengguna yang terikat estate tidak boleh menulis
        // baris milik estate lain hanya dengan mengirim housing_estate_id.
        static::saving(function (Model $model): void {
            $model->applyEstateWriteScope();
        });
    }

    public function estateScopeColumn(): string
    {
        return 'housing_estate_id';
    }

    public function applyEstateScope(Builder $query, int $estateId): void
    {
        $column = $this->estateScopeColumn();

        $query->where(fn (Builder $q) => $q
            ->where($column, $estateId)
            ->orWhereNull($column));
    }

    /**
     * Kunci kolom estate pada baris yang sedang disimpan.
     *
     * Hanya berlaku saat membuat baris baru atau saat kolom estate benar-benar
     * diubah, sehingga update kolom lain (mis. last_login_at) tidak ikut terkunci.
     *
     * - Platform (super_admin / CLI) → nilai dari input dibiarkan.
     * - Estate X → dipaksa menjadi X, apa pun input pengguna.
     * - Terikat tanpa hunian → ditolak, karena tidak boleh menulis data global.
     */
    public function applyEstateWriteScope(): void
    {
        $column = $this->estateScopeColumn();

        if (! $this->isFillable($column)) {
            return;
        }

        // Baris baru: selalu kunci, termasuk saat kolom estate tidak diisi,
        // supaya tenant tidak bisa diam-diam membuat baris global.
        if (! $this->exists) {
            $this->lockEstateColumn($column);

            return;
        }

        // Baris lama: hanya kunci bila ada yang benar-benar memindahkan baris
        // ini ke estate lain (update kolom lain tidak tersentuh).
        if ($this->isDirty($column)) {
            $this->lockEstateColumn($column);
        }
    }

    protected function lockEstateColumn(string $column): void
    {
        $estateId = CurrentEstate::id();

        if ($estateId === null) {
            return;
        }

        if ($estateId === CurrentEstate::NONE) {
            abort(403, 'Akun Anda belum terikat ke perumahan mana pun.');
        }

        $this->setAttribute($column, $estateId);
    }
}
