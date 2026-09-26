<?php

namespace App\Livewire\Resident;

use App\Models\Billing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Dashboard extends Component
{
    #[Layout('layouts.resident', ['title' => 'Halo'])]
    public function render()
    {
        $user = Auth::user()->loadMissing(['resident.houseResidents.house.block', 'role']);

        $house = $user->resident?->houseResidents->firstWhere('is_primary', true)?->house
            ?? $user->resident?->houseResidents->first()?->house;

        $billings = collect();

        if ($user->resident_id) {
            $residentId = (int) $user->resident_id;

            $billings = Billing::query()
                ->where(fn ($query) => $query
                    ->where('resident_id', $residentId)
                    ->orWhereHas('house.houseResidents', fn ($relation) => $relation
                        ->where('resident_id', $residentId)
                        ->where('status', 'active')))
                ->with(['house.block', 'iplRate', 'waterRate'])
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->get();
        }

        $outstanding = $billings->whereIn('status', ['unpaid', 'partial']);

        $overdue = $outstanding
            ->filter(fn ($billing) => $billing->isOverdue())
            ->sortBy(fn ($billing) => [$billing->due_date?->toDateString() ?? '', $billing->period_year, $billing->period_month])
            ->values();

        $currentBillings = $billings
            ->filter(fn ($billing) => (int) $billing->period_month === (int) now()->month
                && (int) $billing->period_year === (int) now()->year)
            ->values();

        $currentBilling = $currentBillings->first();

        return view('livewire.resident.dashboard', [
            'user' => $user,
            'house' => $house,
            'currentBilling' => $currentBilling,
            'currentBillings' => $currentBillings,
            'overdueBillings' => $overdue,
            'outstandingAmount' => (float) $outstanding->sum(fn ($billing) => $billing->remaining()),
            'outstandingCount' => $outstanding->count(),
        ]);
    }
}
