<?php

namespace App\Livewire\Admin\Residents;

use App\Models\House;
use App\Models\Resident;
use App\Models\ResidentInvitation;
use App\Services\ResidentInvitationService;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/** Daftar + buat + batalkan undangan warga (link bertoken, share via WA). */
class Invitations extends Component
{
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $house_id = '';

    public ?string $resident_id = '';

    public ?string $lastUrl = null;

    public ?string $lastWaUrl = null;

    public ?string $lastName = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(ResidentInvitationService $service): void
    {
        $this->authorize('create', Resident::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'house_id' => ['required', 'exists:houses,id'],
            'resident_id' => ['nullable', 'exists:residents,id'],
        ], [
            'name.required' => 'Nama warga wajib diisi.',
            'house_id.required' => 'Rumah wajib dipilih.',
        ]);

        try {
            $result = $service->create(auth()->user(), $data);
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $invitation = $result['invitation'];
        $this->lastUrl = $result['url'];
        $this->lastWaUrl = $service->waLink($invitation, $result['url']);
        $this->lastName = $invitation->name;

        $this->reset(['name', 'phone', 'email', 'house_id', 'resident_id']);
        session()->flash('status', 'Undangan untuk '.$invitation->name.' dibuat. Bagikan link via WA.');
    }

    public function copyLink(string $url): void
    {
        $this->lastUrl = $url;
    }

    public function revoke(int $id, ResidentInvitationService $service): void
    {
        $this->authorize('create', Resident::class);

        $invitation = ResidentInvitation::findOrFail($id);

        try {
            $service->revoke($invitation, auth()->user());
            session()->flash('status', 'Undangan dibatalkan.');
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    #[Layout('layouts.admin', ['title' => 'Undang Warga'])]
    public function render(ResidentInvitationService $service)
    {
        $estateId = CurrentEstate::id();

        $invitations = ResidentInvitation::with(['house.block', 'resident', 'inviter'])
            ->when($estateId !== null && $estateId !== CurrentEstate::NONE, fn (Builder $q) => $q->where('housing_estate_id', $estateId))
            ->when($this->search !== '', fn (Builder $q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest('id')
            ->paginate(10);

        // Lengkapi tiap baris dengan URL + WA (token mentah tidak disimpan,
        // jadi untuk baris lama tampilkan ulang hanya bila masih pending via reissue).
        $invitations->getCollection()->transform(function (ResidentInvitation $inv) {
            $inv->share_url = null;

            return $inv;
        });

        return view('livewire.admin.residents.invitations', [
            'invitations' => $invitations,
            'houses' => House::with('block')->orderBy('house_number')->get(),
            'waHelper' => $service,
        ]);
    }
}
