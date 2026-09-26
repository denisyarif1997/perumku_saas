<?php

namespace App\Livewire\Admin\Water;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\House;
use App\Models\HousingEstate;
use App\Models\WaterMeterReading;
use App\Models\WaterRate;
use App\Services\WaterBillingService;
use App\Support\Currency;
use App\Support\ImageCompressor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

class Readings extends Component
{
    use WithFileUploads;

    public string $period_month = '';

    public string $period_year = '';

    public string $housing_estate_id = '';

    public string $due_day = '10';

    public string $water_rate_id = '';

    public string $houseSearch = '';

    public array $meters = [];

    public array $starts = [];

    public array $photos = [];

    public ?array $result = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);
        $this->period_month = (string) now()->month;
        $this->period_year = (string) now()->year;
    }

    public function updatedHousingEstateId(): void
    {
        $this->houseSearch = '';
        $this->meters = [];
        $this->photos = [];
        $this->result = null;
    }

    public function updatedPeriodMonth(): void
    {
        // Reset agar input terisi ulang dari draft periode terpilih.
        $this->meters = [];
        $this->starts = [];
        $this->photos = [];
    }

    public function updatedPeriodYear(): void
    {
        $this->meters = [];
        $this->starts = [];
        $this->photos = [];
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['period_month', 'period_year', 'due_day', 'water_rate_id', 'houseSearch'], true)) {
            $this->result = null;
        }
    }

    protected function houseQuery()
    {
        $eid = $this->housing_estate_id ? (int) $this->housing_estate_id : null;

        return House::query()->where('status', 'active')
            ->when($eid, fn ($q) => $q->where('housing_estate_id', $eid))
            ->when($this->houseSearch, function ($q) {
                $s = '%'.$this->houseSearch.'%';
                $q->where(fn ($qq) => $qq->where('house_number', 'like', $s)->orWhere('address', 'like', $s));
            });
    }

    protected function meterRows(): array
    {
        $rows = [];
        foreach ($this->meters as $hid => $val) {
            if ($val === null || $val === '') {
                continue;
            }
            $rows[(int) $hid] = $val;
        }

        return $rows;
    }

    /**
     * Override meter awal yang diisi manual [house_id => angka].
     */
    protected function startRows(): array
    {
        $rows = [];
        foreach ($this->starts as $hid => $val) {
            if ($val === null || $val === '') {
                continue;
            }
            $rows[(int) $hid] = (float) $val;
        }

        return $rows;
    }

    protected function validateForm(): void
    {
        $this->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'housing_estate_id' => ['nullable', 'exists:housing_estates,id'],
            'water_rate_id' => ['nullable', 'exists:water_rates,id'],
            'meters' => ['nullable', 'array'],
            'meters.*' => ['nullable', 'numeric', 'min:0'],
            'starts' => ['nullable', 'array'],
            'starts.*' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['nullable', 'image', 'max:12288'],
        ], [
            'meters.*.numeric' => 'Angka meter harus berupa angka.',
            'photos.*.image' => 'Foto meter harus berupa gambar (JPG/PNG/WebP).',
            'photos.*.max' => 'Ukuran foto maksimal 12MB.',
        ]);
    }

    /**
     * Kompres semua foto meteran terpilih menjadi JPEG <= 1MB lalu simpan
     * sebagai file di disk public. Mengembalikan null jika ada foto yang
     * gagal diproses (error sudah dicatat).
     *
     * @return array<int, string>|null [house_id => path file]
     */
    protected function photoPayload(): ?array
    {
        $payload = [];
        foreach ($this->photos as $key => $file) {
            // File bisa berupa array bila dipilih ulang — ambil yang terakhir.
            if (is_array($file) || $file instanceof \Traversable) {
                $file = collect($file)->filter(fn ($f) => $f instanceof UploadedFile)->last();
            }
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $binary = @file_get_contents($file->getRealPath());
            $compressed = $binary === false ? null : ImageCompressor::compressToJpeg($binary);
            if ($compressed === null) {
                $this->addError('photos.'.$key, 'Foto tidak dapat diproses. Gunakan JPG/PNG/WebP.');

                return null;
            }
            $path = 'water-meters/'.now()->format('Ymd').'-'.$key.'-'.Str::random(10).'.jpg';
            Storage::disk('public')->put($path, $compressed['data']);
            $payload[(int) $key] = $path;
        }

        return $payload;
    }

    protected function hasSelectedPhotos(): bool
    {
        foreach ($this->photos as $file) {
            if (is_array($file) || $file instanceof \Traversable) {
                $file = collect($file)->filter(fn ($f) => $f instanceof UploadedFile)->last();
            }
            if ($file instanceof UploadedFile) {
                return true;
            }
        }

        return false;
    }

    protected function clearFilled(): void
    {
        foreach ($this->meters as $hid => $val) {
            if ($val === null || $val === '') {
                continue;
            }
            unset($this->meters[$hid], $this->photos[$hid]);
        }
    }

    /**
     * Simpan bacaan meter (draft) + foto tanpa membuat tagihan.
     */
    public function save(WaterBillingService $svc): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);
        $year = (int) $this->period_year ?: now()->year;
        $month = (int) $this->period_month ?: now()->month;
        $eid = $this->housing_estate_id ? (int) $this->housing_estate_id : null;
        $rid = $this->water_rate_id ? (int) $this->water_rate_id : null;

        $this->validateForm();
        $rows = $this->meterRows();
        if ($rows === [] && ! $this->hasSelectedPhotos()) {
            session()->flash('error', 'Isi minimal 1 angka meter akhir.');

            return;
        }
        $photos = $this->photoPayload();
        if ($photos === null) {
            return;
        }

        $res = $svc->saveReadings($year, $month, $eid, auth()->id(), $rid, $rows, $photos, $this->startRows());
        $this->clearFilled();
        $this->result = null;

        $summary = [];
        if ($res['saved'] > 0) {
            $summary[] = $res['saved'].' bacaan meter tersimpan (draft) untuk '.Currency::period($year, $month);
        }
        if (($res['attached'] ?? 0) > 0) {
            $summary[] = $res['attached'].' foto dilampirkan ke bacaan draft';
        }
        if ($res['skipped'] > 0) {
            $summary[] = $res['skipped'].' rumah sudah ditagih sebelumnya';
        }
        if (($res['photo_dropped'] ?? 0) > 0) {
            $summary[] = $res['photo_dropped'].' foto ditolak (rumah belum punya bacaan draft — isi meter akhir dulu)';
        }
        if ($res['invalid'] > 0) {
            $summary[] = $res['invalid'].' bacaan ditolak (angka mundur)';
        }

        if ($res['saved'] > 0 || ($res['attached'] ?? 0) > 0) {
            session()->flash('success', implode('. ', $summary).'. Klik Generate Tagihan Air saat siap.');
        } elseif ($res['skipped'] > 0) {
            session()->flash('info', implode('. ', $summary));
        } else {
            session()->flash('error', 'Tidak ada yang tersimpan. '.implode('. ', $summary));
        }

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'create', 'module' => 'water_meter_readings',
            'description' => 'Simpan bacaan meter air '.Currency::period($year, $month)
                .' — tersimpan: '.$res['saved'].', sudah ditagih: '.$res['skipped'].', ditolak: '.$res['invalid'],
        ]);
    }

    /**
     * Simpan bacaan yang diisi lalu buat tagihan air dari seluruh draft periode tsb.
     */
    public function generate(WaterBillingService $svc): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);
        $year = (int) $this->period_year ?: now()->year;
        $month = (int) $this->period_month ?: now()->month;
        $due = (int) $this->due_day ?: 10;
        $eid = $this->housing_estate_id ? (int) $this->housing_estate_id : null;
        $rid = $this->water_rate_id ? (int) $this->water_rate_id : null;

        $this->validateForm();
        $rows = $this->meterRows();
        $photos = [];
        if ($rows !== [] || $this->hasSelectedPhotos()) {
            $photos = $this->photoPayload();
            if ($photos === null) {
                return;
            }
        }

        $savedOut = $rows !== []
            ? $svc->saveReadings($year, $month, $eid, auth()->id(), $rid, $rows, $photos, $this->startRows())
            : ['saved' => 0, 'skipped' => 0, 'no_rate' => 0, 'invalid' => 0];
        $gen = $svc->generateFromDrafts($year, $month, $eid, $due, auth()->id(), $rid);

        $made = $gen['created'] + $gen['restored'];
        $this->result = [
            'created' => $gen['created'], 'restored' => $gen['restored'],
            'skipped' => $savedOut['skipped'], 'invalid' => $savedOut['invalid'],
            'drafts_saved' => $savedOut['saved'],
        ];
        $this->clearFilled();

        if ($made > 0) {
            session()->flash('success', $made.' tagihan air dibuat untuk '.Currency::period($year, $month).'.');
        } elseif ($savedOut['saved'] > 0) {
            session()->flash('info', 'Bacaan tersimpan sebagai draft, tetapi tagihan belum dibuat. Pastikan tarif air tersedia.');
        } elseif ($savedOut['skipped'] > 0) {
            session()->flash('info', 'Semua bacaan periode ini sudah ditagih.');
        } else {
            session()->flash('error', 'Tidak ada tagihan dibuat. Isi meter akhir atau simpan bacaan terlebih dahulu.');
        }

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'generate', 'module' => 'water_billings',
            'description' => 'Generate tagihan air '.Currency::period($year, $month)
                .' — dibuat: '.$gen['created'].', dipulihkan: '.$gen['restored']
                .', draft tersimpan: '.$savedOut['saved'].', sudah ditagih: '.$savedOut['skipped']
                .', tanpa tarif: '.($savedOut['no_rate'] + $gen['no_rate']),
        ]);
    }

    public function clearSelectedPhoto(int $houseId): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);

        if (array_key_exists($houseId, $this->photos)) {
            unset($this->photos[$houseId]);
        }

        $this->result = null;
    }

    public function removePhoto(int $houseId): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);
        $reading = WaterMeterReading::where('house_id', $houseId)
            ->where('period_year', (int) $this->period_year)
            ->where('period_month', (int) $this->period_month)->first();
        if (! $reading || ! $reading->photo_path) {
            session()->flash('error', 'Tidak ada foto meter untuk dihapus.');

            return;
        }
        if ($reading->status === 'billed') {
            session()->flash('error', 'Foto bacaan yang sudah ditagih tidak bisa dihapus.');

            return;
        }
        if ($reading->photo_path) {
            Storage::disk('public')->delete($reading->photo_path);
        }
        $reading->update(['photo_path' => null]);
        session()->flash('info', 'Foto meter dihapus.');
    }

    #[Layout('layouts.admin', ['title' => 'Catat Meter Air'])]
    public function render()
    {
        $eid = $this->housing_estate_id ? (int) $this->housing_estate_id : null;
        $month = (int) $this->period_month ?: now()->month;
        $year = (int) $this->period_year ?: now()->year;
        $rid = $this->water_rate_id ? (int) $this->water_rate_id : null;
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $rate = $rid ? WaterRate::find($rid) : WaterRate::forDate($eid, $start);
        $houses = $this->houseQuery()->with(['block', 'estate', 'houseResidents.resident'])
            ->orderBy('housing_block_id')->orderBy('house_number')->get()
            ->map(function (House $h) use ($year, $month) {
                $last = WaterMeterReading::where('house_id', $h->id)
                    ->where(fn ($q) => $q->where('period_year', '<', $year)
                        ->orWhere(fn ($qq) => $qq->where('period_year', $year)->where('period_month', '<', $month)))
                    ->orderByDesc('period_year')->orderByDesc('period_month')
                    ->first(['id', 'period_month', 'period_year', 'photo_path', 'meter_end']);
                $reading = WaterMeterReading::where('house_id', $h->id)
                    ->where('period_year', $year)->where('period_month', $month)
                    ->first(['id', 'status', 'photo_path', 'meter_end', 'usage_m3', 'amount']);

                // Tampilkan kembali bacaan draft terakhir pada input meter akhir.
                if ($reading && $reading->status === 'draft' && ! array_key_exists($h->id, $this->meters)) {
                    $this->meters[$h->id] = (string) $reading->meter_end;
                }

                $billed = Billing::withTrashed()->where('house_id', $h->id)
                    ->where('period_year', $year)->where('period_month', $month)
                    ->where('billing_type', 'water')->exists();

                return ['id' => $h->id, 'label' => $h->fullLabel(), 'estate' => $h->estate?->name,
                    'resident' => $h->houseResidents->first()?->resident?->name ?? '-',
                    'last' => $last ? (float) $last->meter_end : 0.0, 'billed' => $billed,
                    'saved' => $reading?->status,
                    'saved_end' => $reading ? (float) $reading->meter_end : null,
                    'saved_usage' => $reading ? (float) $reading->usage_m3 : null,
                    'saved_amount' => $reading ? (float) $reading->amount : null,
                    'photo_url' => $reading?->photoUrl(),
                    'last_period' => $last ? Currency::period((int) $last->period_year, (int) $last->period_month) : null,
                    'last_photo_url' => $last?->photoUrl()];
            });

        return view('livewire.admin.water.readings', [
            'estates' => HousingEstate::orderBy('name')->get(),
            'rates' => WaterRate::with('estate')->active()->orderByDesc('effective_date')->get(),
            'houses' => $houses, 'rate' => $rate,
        ]);
    }
}
