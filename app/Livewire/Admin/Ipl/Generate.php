<?php

namespace App\Livewire\Admin\Ipl;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\House;
use App\Models\HousingEstate;
use App\Models\IplRate;
use App\Services\IplBillingService;
use App\Support\Currency;
use App\Support\CurrentEstate;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Generate extends Component
{
    public string $period_month = '';

    public string $period_year = '';

    public string $housing_estate_id = '';

    public string $due_day = '10';

    public string $ipl_rate_id = '';

    /** @var array<int|string> */
    public array $selectedHouses = [];

    public string $houseSearch = '';

    public bool $selectAll = true;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);

        $this->period_month = (string) now()->month;
        $this->period_year = (string) now()->year;
        $this->selectedHouses = $this->defaultSelectedHouses();
    }

    public function updatedHousingEstateId(): void
    {
        $this->houseSearch = '';
        $this->selectedHouses = $this->defaultSelectedHouses();
        $this->selectAll = true;
        $this->result = null;
    }

    public function updatedPeriodMonth(): void
    {
        $this->result = null;
    }

    public function updatedPeriodYear(): void
    {
        $this->result = null;
    }

    public function updatedDueDay(): void
    {
        $this->result = null;
    }

    public function updatedIplRateId(): void
    {
        $this->result = null;
    }

    public function updatedSelectedHouses(): void
    {
        $total = $this->houseQuery()->count();
        $this->selectAll = $total > 0 && count($this->selectedHouses) === $total;
        $this->result = null;
    }

    public function updatedHouseSearch(): void
    {
        $this->result = null;
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedHouses = [];
            $this->selectAll = false;
        } else {
            $this->selectedHouses = $this->defaultSelectedHouses();
            $this->selectAll = true;
        }

        $this->result = null;
    }

    protected function houseQuery()
    {
        $estateId = $this->housing_estate_id ? (int) $this->housing_estate_id : null;

        // Kunci ke estate sendiri: admin estate tidak boleh memilih rumah/estate lain.
        $lockedEstateId = CurrentEstate::id();
        if ($lockedEstateId !== null && $lockedEstateId !== CurrentEstate::NONE) {
            $estateId = $lockedEstateId;
        }

        return House::query()
            ->where('status', 'active')
            ->when($estateId, fn ($q) => $q->where('housing_estate_id', $estateId))
            ->when($this->houseSearch, function ($q) {
                $s = '%'.$this->houseSearch.'%';
                $q->where(fn ($qq) => $qq
                    ->where('house_number', 'like', $s)
                    ->orWhere('address', 'like', $s)
                    ->orWhereHas('houseResidents.resident', fn ($r) => $r->where('name', 'like', $s)));
            });
    }

    /** @return array<int> */
    protected function defaultSelectedHouses(): array
    {
        return $this->houseQuery()->pluck('houses.id')->map(fn ($id) => (int) $id)->all();
    }

    public function generate(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-billing'), 403);

        $data = $this->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2020,2100'],
            'housing_estate_id' => ['nullable', 'exists:housing_estates,id'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'ipl_rate_id' => ['nullable', 'exists:ipl_rates,id'],
            'selectedHouses' => ['required', 'array', 'min:1'],
            'selectedHouses.*' => ['integer', 'exists:houses,id'],
        ], [
            'period_month.required' => 'Bulan periode wajib dipilih.',
            'period_year.required' => 'Tahun periode wajib dipilih.',
            'due_day.between' => 'Tanggal jatuh tempo harus antara 1 sampai 28.',
            'selectedHouses.required' => 'Pilih minimal 1 warga/rumah yang akan ditagih.',
            'selectedHouses.min' => 'Pilih minimal 1 warga/rumah yang akan ditagih.',
        ]);

        $estateId = $data['housing_estate_id'] ? (int) $data['housing_estate_id'] : null;
        $month = (int) $data['period_month'];
        $year = (int) $data['period_year'];
        $rateId = $data['ipl_rate_id'] ? (int) $data['ipl_rate_id'] : null;

        // Paksa estate sendiri + pastikan rumah & tarif milik estate yang sama.
        $lockedEstateId = CurrentEstate::id();
        if ($lockedEstateId !== null && $lockedEstateId !== CurrentEstate::NONE) {
            $estateId = $lockedEstateId;
        }
        $houseIds = array_map('intval', $data['selectedHouses']);
        abort_unless(
            House::whereIn('id', $houseIds)->where('housing_estate_id', $estateId)->count() === count($houseIds),
            403,
            'Rumah bukan bagian dari perumahan Anda.'
        );
        if ($rateId) {
            abort_unless(
                IplRate::whereKey($rateId)
                    ->where(fn ($q) => $q->where('housing_estate_id', $estateId)->orWhereNull('housing_estate_id'))
                    ->exists(),
                403,
                'Tarif bukan milik perumahan Anda.'
            );
        }

        $result = app(IplBillingService::class)->generate(
            $year,
            $month,
            $estateId,
            (int) $data['due_day'],
            auth()->id(),
            $rateId,
            $houseIds,
        );

        $this->result = $result;

        $rateLabel = $rateId
            ? (IplRate::find($rateId)?->name ?? '-')
            : ($result['rate_name'] ?? 'tarif berlaku otomatis');

        ActivityLog::record([
            'user_id' => auth()->id(),
            'action' => 'generate',
            'module' => 'billings',
            'description' => 'Generate tagihan Iuran '.Currency::period($year, $month)
                .' [tarif: '.$rateLabel.']'
                .' — dibuat: '.$result['created']
                .', dipulihkan: '.$result['restored']
                .', dilewati: '.$result['skipped']
                .', tanpa tarif: '.$result['no_rate'],
        ]);

        $processed = $result['created'] + $result['restored'];

        if ($processed > 0) {
            session()->flash('success', $processed.' tagihan Iuran berhasil dibuat untuk periode '.Currency::period($year, $month).' (tarif: '.$rateLabel.').');
        } elseif ($result['skipped'] > 0) {
            session()->flash('info', 'Rumah yang dipilih sudah memiliki tagihan untuk tarif & periode ini. Tidak ada duplikat yang dibuat. Untuk menagih tarif baru, pilih tarif yang berbeda.');
        } else {
            session()->flash('error', 'Tidak ada tagihan yang dibuat. Pastikan tarif dipilih/berlaku dan minimal 1 warga dicentang.');
        }
    }

    #[Layout('layouts.admin', ['title' => 'Generate Tagihan Iuran'])]
    public function render()
    {
        // Samakan dengan houseQuery()/generate(): admin estate dikunci ke estate sendiri.
        $lockedEstateId = CurrentEstate::id();
        if ($lockedEstateId !== null && $lockedEstateId !== CurrentEstate::NONE) {
            $this->housing_estate_id = (string) $lockedEstateId;
        }
        $estateId = $this->housing_estate_id ? (int) $this->housing_estate_id : null;
        $month = (int) $this->period_month ?: (int) now()->month;
        $year = (int) $this->period_year ?: (int) now()->year;
        $rateId = $this->ipl_rate_id ? (int) $this->ipl_rate_id : null;
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();

        $activeHouses = House::query()
            ->where('status', 'active')
            ->when($estateId, fn ($q) => $q->where('housing_estate_id', $estateId))
            ->count();

        $alreadyBilled = Billing::query()
            ->withTrashed()
            ->forPeriod($year, $month)
            ->when($estateId, fn ($q) => $q->whereHas('house', fn ($h) => $h->where('housing_estate_id', $estateId)))
            ->when($rateId, fn ($q) => $q->where('ipl_rate_id', $rateId))
            ->count();

        $houses = $this->houseQuery()
            ->with(['block', 'estate', 'houseResidents.resident'])
            ->orderBy('housing_block_id')
            ->orderBy('house_number')
            ->get()
            ->map(function (House $house) use ($year, $month, $rateId) {
                return [
                    'id' => $house->id,
                    'label' => ($house->block ? $house->block->code.'-' : '').$house->house_number,
                    'address' => $house->address,
                    'estate' => $house->estate?->name,
                    'resident' => $house->houseResidents->first()?->resident?->name ?? '-',
                    'billed' => Billing::withTrashed()
                        ->where('house_id', $house->id)
                        ->where('period_year', $year)
                        ->where('period_month', $month)
                        ->when($rateId, fn ($q) => $q->where('ipl_rate_id', $rateId))
                        ->exists(),
                ];
            });

        $rate = $rateId ? IplRate::find($rateId) : IplRate::forDate($estateId, $periodStart);

        // Dropdown estate hanya menampilkan milik sendiri agar tidak bocor/enum lintas-tenant.
        $estates = ($lockedEstateId !== null && $lockedEstateId !== CurrentEstate::NONE)
            ? HousingEstate::whereKey($lockedEstateId)->orderBy('name')->get()
            : HousingEstate::orderBy('name')->get();

        // Dropdown tarif juga dibatasi ke global + estate sendiri.
        $rates = IplRate::with('estate')->active()
            ->when(
                $lockedEstateId !== null && $lockedEstateId !== CurrentEstate::NONE,
                fn ($q) => $q->where(fn ($qq) => $qq->where('housing_estate_id', $lockedEstateId)->orWhereNull('housing_estate_id'))
            )
            ->orderByDesc('effective_date')->get();

        return view('livewire.admin.ipl.generate', [
            'estates' => $estates,
            'rates' => $rates,
            'activeHouses' => $activeHouses,
            'alreadyBilled' => $alreadyBilled,
            'houses' => $houses,
            'rate' => $rate,
        ]);
    }
}
