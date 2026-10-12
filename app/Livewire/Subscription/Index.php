<?php

namespace App\Livewire\Subscription;

use App\Models\EstateSubscriptionInvoice;
use App\Models\HousingEstate;
use App\Services\Billing\GatewayFactory;
use App\Services\Billing\SubscriptionService;
use App\Support\Currency;
use App\Support\CurrentEstate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman Langganan untuk admin perumahan: status trial/aktif, pemakaian
 * kuota user, daftar invoice, bayar paket & bayar kelebihan user.
 */
class Index extends Component
{
    public string $notice = '';

    public function payBase(SubscriptionService $service): void
    {
        $estate = $this->estate();

        abort_unless($estate && auth()->user()?->hasPermission('manage-user'), 403);

        $invoice = $service->ensureBaseInvoice($estate);
        $this->startPayment($invoice);
    }

    public function payOverage(SubscriptionService $service): void
    {
        $estate = $this->estate();

        abort_unless($estate && auth()->user()?->hasPermission('manage-user'), 403);

        try {
            $invoice = $service->createOverageInvoice($estate);
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->startPayment($invoice);
    }

    public function pay(int $id): void
    {
        $invoice = EstateSubscriptionInvoice::query()
            ->where('housing_estate_id', CurrentEstate::id())
            ->findOrFail($id);

        abort_unless(auth()->user()?->hasPermission('manage-user'), 403);

        $this->startPayment($invoice);
    }

    protected function startPayment(EstateSubscriptionInvoice $invoice): void
    {
        if ($invoice->status === 'paid') {
            session()->flash('status', 'Invoice '.$invoice->number.' sudah lunas.');

            return;
        }

        $gateway = GatewayFactory::make($invoice->gateway);
        $result = $gateway->createPayment($invoice);

        if (! empty($result['payment_url'])) {
            $invoice->forceFill([
                'payment_url' => $result['payment_url'],
                'gateway_reference' => $result['reference'] ?? $invoice->gateway_reference,
                'status' => 'pending',
            ])->save();

            $this->redirect($result['payment_url'], navigate: false);

            return;
        }

        if (! empty($result['reference'])) {
            $invoice->forceFill([
                'gateway_reference' => $result['reference'],
                'status' => 'pending',
            ])->save();
        }

        session()->flash(
            'status',
            'Invoice '.$invoice->number.' ('.Currency::rupiah((float) $invoice->total_amount).') siap dibayar. '.
            ($result['error'] ?? 'Lakukan pembayaran lalu konfirmasi ke superadmin bila memakai mode manual.')
        );
    }

    protected function estate(): ?HousingEstate
    {
        $id = CurrentEstate::id();

        if ($id === null || $id === CurrentEstate::NONE) {
            return null;
        }

        return HousingEstate::query()->withoutGlobalScopes()->find($id);
    }

    #[Layout('layouts.admin', ['title' => 'Langganan'])]
    public function render(SubscriptionService $service)
    {
        $estate = $this->estate();
        abort_unless($estate, 403);

        $subscription = $service->forEstate($estate->id) ?? $service->startTrial($estate);
        $subscription = $service->refreshStatus($subscription);

        $activeUsers = $service->activeUserCount($estate);
        $included = (int) $subscription->included_users;

        return view('livewire.subscription.index', [
            'estate' => $estate,
            'subscription' => $subscription,
            'activeUsers' => $activeUsers,
            'included' => $included,
            'overage' => max(0, $activeUsers - $included),
            'daysLeft' => $subscription->trialDaysLeft(),
            'monthlyPrice' => $service->monthlyPrice(),
            'overagePerUser' => $service->overagePerUser(),
            'invoices' => EstateSubscriptionInvoice::query()
                ->where('housing_estate_id', $estate->id)
                ->latest('id')->paginate(10),
        ]);
    }
}
