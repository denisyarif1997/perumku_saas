<?php

namespace App\Livewire\Admin;

use App\Models\Billing;
use App\Models\House;
use App\Models\HousingBlock;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\User;
use App\Support\Currency;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Dashboard extends Component
{
    #[Layout('layouts.admin', ['title' => 'Dashboard'])]
    public function render()
    {
        $user = auth()->user();

        $canManageHouses = $user->hasPermission('manage-houses');
        $canManageResidents = $user->hasPermission('manage-residents');
        $canManageUsers = $user->hasPermission('manage-user');
        $canManageBilling = $user->hasPermission('manage-billing');
        $canViewPayments = $user->hasPermission('manage-payment', 'verify-payment');

        $year = now()->year;
        $month = now()->month;

        $periodBillings = ($canManageBilling || $canViewPayments) ? Billing::forPeriod($year, $month) : null;

        $totalHouses = $canManageHouses ? House::count() : 0;
        $totalResidents = $canManageResidents ? Resident::count() : 0;
        $totalBlocks = $canManageHouses ? HousingBlock::count() : 0;
        $totalUsers = $canManageUsers ? User::count() : 0;
        $activeHouses = $canManageHouses ? House::where('status', 'active')->count() : 0;
        $activeResidents = $canManageResidents ? Resident::where('status', 'active')->count() : 0;
        $inactiveHouses = $canManageHouses ? House::where('status', 'inactive')->count() : 0;

        $periodTotal = $periodBillings ? (float) (clone $periodBillings)->where('status', '!=', 'cancelled')->sum('total') : 0.0;
        $periodPaid = $periodBillings ? (float) (clone $periodBillings)->where('status', '!=', 'cancelled')->sum('paid_amount') : 0.0;
        $outstanding = $periodBillings
            ? (float) (clone $periodBillings)->outstanding()->sum('total') - (float) (clone $periodBillings)->outstanding()->sum('paid_amount')
            : 0.0;

        $overdueBillings = $canManageBilling || $canViewPayments
            ? Billing::whereIn('status', ['unpaid', 'partial'])->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())->with(['house.block', 'resident'])->get()
            : collect();

        $collectionRate = $periodTotal > 0 ? round(($periodPaid / $periodTotal) * 100, 1) : 0;
        $thisMonthPayments = $canViewPayments
            ? Payment::whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->where('status', 'verified')->sum('amount')
            : 0.0;

        return view('livewire.admin.dashboard', [
            'canManageHouses' => $canManageHouses,
            'canManageResidents' => $canManageResidents,
            'canManageUsers' => $canManageUsers,
            'canManageBilling' => $canManageBilling,
            'canViewPayments' => $canViewPayments,

            'totalHouses' => $totalHouses,
            'totalResidents' => $totalResidents,
            'totalBlocks' => $totalBlocks,
            'totalUsers' => $totalUsers,
            'activeHouses' => $activeHouses,
            'activeResidents' => $activeResidents,
            'inactiveHouses' => $inactiveHouses,

            'periodLabel' => Currency::period($year, $month),
            'periodTotal' => $periodTotal,
            'periodPaid' => $periodPaid,
            'periodOutstanding' => max(0, $outstanding),
            'periodUnpaidCount' => $periodBillings ? (clone $periodBillings)->outstanding()->count() : 0,
            'collectionRate' => $collectionRate,
            'thisMonthPayments' => $thisMonthPayments,
            'pendingPayments' => $canViewPayments ? Payment::where('status', 'pending')->count() : 0,
            'pendingPaymentsAmount' => $canViewPayments ? (float) Payment::where('status', 'pending')->sum('amount') : 0.0,
            'overdueBillings' => $overdueBillings,
            'overdueBillingsCount' => $overdueBillings->count(),
            'overdueBillingsAmount' => (float) $overdueBillings->sum('total'),

            'recentPayments' => $canViewPayments
                ? Payment::with(['resident', 'billing.house.block'])->orderByDesc('id')->take(5)->get()
                : collect(),

            'recentHouses' => $canManageHouses
                ? House::with(['block', 'houseResidents.resident'])->latest()->take(5)->get()
                : collect(),

            'recentResidents' => $canManageResidents
                ? Resident::latest()->take(5)->get()
                : collect(),
        ]);
    }
}
