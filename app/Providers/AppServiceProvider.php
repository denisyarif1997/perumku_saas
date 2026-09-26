<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Billing;
use App\Models\Complaint;
use App\Models\House;
use App\Models\HousingEstate;
use App\Models\Payment;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\BillingPolicy;
use App\Policies\ComplaintPolicy;
use App\Policies\HousePolicy;
use App\Policies\HousingEstatePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PostCommentPolicy;
use App\Policies\PostPolicy;
use App\Policies\ResidentPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(House::class, HousePolicy::class);
        Gate::policy(HousingEstate::class, HousingEstatePolicy::class);
        Gate::policy(Resident::class, ResidentPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Billing::class, BillingPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(Complaint::class, ComplaintPolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(PostComment::class, PostCommentPolicy::class);

        Gate::before(function ($user, $ability) {
            if ($user instanceof User && $user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });

        // {{-- @rupiah($billing->total) --}}
        Blade::directive('rupiah', fn (string $expression): string => "<?php echo e(\\App\\Support\\Currency::rupiah({$expression})); ?>");

        // {{-- @periode($billing->period_year, $billing->period_month) --}}
        Blade::directive('periode', fn (string $expression): string => "<?php echo e(\\App\\Support\\Currency::period({$expression})); ?>");
    }
}
