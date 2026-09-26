<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Models\Scopes\BelongsToEstateScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use BelongsToEstate, SoftDeletes;

    /** Jumlah pilihan polling dibatasi supaya warga tidak bingung memilih. */
    public const POLL_MAX_OPTIONS = 6;

    protected $fillable = [
        'housing_estate_id', 'resident_id', 'user_id',
        'title', 'body', 'category', 'is_pinned',
        'is_poll', 'poll_type', 'poll_options', 'poll_closes_at', 'poll_is_closed',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_poll' => 'boolean',
            'poll_options' => 'array',
            'poll_closes_at' => 'datetime',
            'poll_is_closed' => 'boolean',
        ];
    }

    public function estate(): BelongsTo
    {
        return $this->belongsTo(HousingEstate::class, 'housing_estate_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class)->orderBy('created_at');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PostPollVote::class);
    }

    public function scopePinnedFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->orderByDesc('created_at');
    }

    public function scopeInCategory(Builder $query, ?string $category): Builder
    {
        return $category ? $query->where('category', $category) : $query;
    }

    /**
     * Daftar pilihan polling sebagai ['o1' => 'Setuju', 'o2' => 'Tidak setuju'].
     *
     * Bentuk tersimpan adalah daftar [['key' => 'o1', 'label' => 'Setuju'], ...]
     * dan bukan objek asosiatif, karena MySQL mengurutkan ulang kunci objek
     * JSON sedangkan urutan pilihan harus sama seperti saat dibuat.
     *
     * @return array<string, string>
     */
    public function pollOptions(): array
    {
        $options = [];

        foreach ((array) ($this->poll_options ?? []) as $index => $option) {
            $label = is_array($option) ? ($option['label'] ?? null) : $option;

            if (! is_string($label) || trim($label) === '') {
                continue;
            }

            $key = is_array($option) && is_string($option['key'] ?? null) && $option['key'] !== ''
                ? $option['key']
                : 'o'.($index + 1);

            $options[$key] = trim($label);
        }

        return $options;
    }

    /**
     * Polling masih menerima suara bila belum ditutup manual dan belum
     * melewati tanggal berakhir yang ditetapkan pembuatnya.
     */
    public function isPollOpen(): bool
    {
        if (! $this->is_poll) {
            return false;
        }

        if ($this->poll_is_closed) {
            return false;
        }

        return $this->poll_closes_at === null || $this->poll_closes_at->isFuture();
    }

    /**
     * Kunci-kunci pilihan yang sudah dipilih satu warga pada polling ini.
     *
     * @return array<int, string>
     */
    public function pollSelectionsFor(?int $residentId): array
    {
        if (! $this->is_poll || $residentId === null) {
            return [];
        }

        return $this->votes()
            ->where('resident_id', $residentId)
            ->pluck('option_key')
            ->all();
    }

    /**
     * Hasil per pilihan: jumlah suara dan persentasenya terhadap total
     * suara yang masuk. Persentase memakai jumlah suara (bukan jumlah
     * pemungut suara) karena polling multi-jawaban bisa menambah suara.
     *
     * @return array<int, array{key: string, label: string, votes: int, percent: int}>
     */
    public function pollResults(): array
    {
        $counts = $this->relationLoaded('votes')
            ? $this->votes->countBy('option_key')
            : $this->votes()
                ->selectRaw('option_key, count(*) as total')
                ->groupBy('option_key')
                ->pluck('total', 'option_key');

        $total = (int) $counts->sum();

        $results = [];

        foreach ($this->pollOptions() as $key => $label) {
            $votes = (int) $counts->get($key, 0);

            $results[] = [
                'key' => $key,
                'label' => $label,
                'votes' => $votes,
                'percent' => $total > 0 ? (int) round($votes / $total * 100) : 0,
            ];
        }

        return $results;
    }

    /**
     * Jumlah warga yang sudah memberikan suara (bukan jumlah baris suara).
     */
    public function pollVotersCount(): int
    {
        return (int) $this->votes()
            ->whereNotNull('resident_id')
            ->distinct()
            ->count('resident_id');
    }

    /**
     * Hasil polling hanya terbuka untuk pengelola forum, pembuat polling, dan
     * warga yang sudah memilih — supaya pilihan warga tidak terpengaruh suara
     * tetangga yang memilih lebih dulu.
     *
     * @param  array<int, string>  $selections
     */
    public function canViewResults(User $user, array $selections): bool
    {
        if ($user->hasPermission('manage-forum')) {
            return true;
        }

        if ($user->resident_id !== null && (int) $this->resident_id === (int) $user->resident_id) {
            return true;
        }

        return $selections !== [];
    }

    /**
     * Hapus suara polling: seluruh suara, atau hanya suara satu warga.
     *
     * Global scope estate sengaja dilepas karena ia menambah WHERE EXISTS ke
     * tabel posts, sedangkan MySQL menolak subquery yang mengacu ke tabel
     * target DELETE. Postingan yang dipanggil selalu milik estate aktif.
     */
    public function deletePollVotes(?int $residentId = null): void
    {
        $query = $this->votes()->withoutGlobalScope(BelongsToEstateScope::class);

        if ($residentId !== null) {
            $query->where('resident_id', $residentId);
        }

        $query->delete();
    }

    /**
     * Nama penulis posting: pakai akun user, fallback ke data warga.
     */
    public function authorName(): string
    {
        return $this->author?->name ?? $this->resident?->name ?? 'Warga';
    }

    /**
     * Rumah penulis posting, contoh: "A-1".
     */
    public function authorHouseLabel(): ?string
    {
        $resident = $this->resident;

        if (! $resident) {
            return null;
        }

        if ($resident->relationLoaded('houseResidents')) {
            $houseResident = $resident->houseResidents->firstWhere('is_primary', true)
                ?? $resident->houseResidents->first();

            return $houseResident?->house?->fullLabel();
        }

        return $resident->primaryHouse()?->fullLabel();
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'pengumuman' => 'Pengumuman',
            'tanya' => 'Tanya Jawab',
            'jual-beli' => 'Jual Beli',
            'fasilitas' => 'Fasilitas',
            'keamanan' => 'Keamanan',
            'kegiatan' => 'Kegiatan',
            default => 'Umum',
        };
    }

    public function categoryColor(): string
    {
        return match ($this->category) {
            'pengumuman' => 'sky',
            'tanya' => 'amber',
            'jual-beli' => 'green',
            'fasilitas' => 'purple',
            'keamanan' => 'red',
            'kegiatan' => 'pink',
            default => 'slate',
        };
    }

    /**
     * Cara memilih pada polling ini, untuk ditampilkan ke warga.
     */
    public function pollTypeLabel(): string
    {
        return $this->poll_type === 'multiple' ? 'Pilih beberapa' : 'Pilih satu';
    }

    /**
     * Daftar kategori yang tersedia untuk form.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'umum' => 'Umum',
            'pengumuman' => 'Pengumuman',
            'tanya' => 'Tanya Jawab',
            'jual-beli' => 'Jual Beli',
            'fasilitas' => 'Fasilitas',
            'keamanan' => 'Keamanan',
            'kegiatan' => 'Kegiatan',
        ];
    }
}
