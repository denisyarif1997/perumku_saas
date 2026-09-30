<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentProofController;
use App\Livewire\Admin\ActivityLogs\Index as ActivityLogIndex;
use App\Livewire\Admin\Blocks\Index as BlockIndex;
use App\Livewire\Admin\Cash\Accounts\Index as CashAccountIndex;
use App\Livewire\Admin\Cash\Transactions\Index as CashTransactionIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Estates\Index as EstateIndex;
use App\Livewire\Admin\Forum\Index as AdminForumIndex;
use App\Livewire\Admin\Houses\Form as HouseForm;
use App\Livewire\Admin\Houses\Index as HouseIndex;
use App\Livewire\Admin\Info\Announcements as InfoAnnouncements;
use App\Livewire\Admin\Info\ComplaintDetail as InfoComplaintDetail;
use App\Livewire\Admin\Info\Complaints as InfoComplaints;
use App\Livewire\Admin\Inventory\Items as InventoryItems;
use App\Livewire\Admin\Inventory\Loans as InventoryLoans;
use App\Livewire\Admin\Ipl\Billings\Index as BillingIndex;
use App\Livewire\Admin\Ipl\Billings\Show as BillingShow;
use App\Livewire\Admin\Ipl\Generate as BillingGenerate;
use App\Livewire\Admin\Ipl\Payments\Index as PaymentIndex;
use App\Livewire\Admin\Ipl\Rates\Index as IplRateIndex;
use App\Livewire\Admin\Residents\Form as ResidentForm;
use App\Livewire\Admin\Residents\Index as ResidentIndex;
use App\Livewire\Admin\Roles\Index as RoleIndex;
use App\Livewire\Admin\Users\Index as UserIndex;
use App\Livewire\Admin\Water\Rates\Index as WaterRateIndex;
use App\Livewire\Admin\Water\Readings as WaterReadings;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Platform\Dashboard as PlatformDashboard;
use App\Livewire\Resident\Cash\Index as ResidentCashIndex;
use App\Livewire\Resident\Chess\Index as ResidentChessIndex;
use App\Livewire\Resident\Chess\Play as ResidentChessPlay;
use App\Livewire\Resident\Complaints\Index as ResidentComplaintIndex;
use App\Livewire\Resident\Complaints\Show as ResidentComplaintShow;
use App\Livewire\Resident\Dashboard as ResidentDashboard;
use App\Livewire\Resident\Forum\Index as ResidentForumIndex;
use App\Livewire\Resident\Forum\Show as ResidentForumShow;
use App\Livewire\Resident\Info\Index as ResidentInfoIndex;
use App\Livewire\Resident\Inventory\Index as ResidentInventoryIndex;
use App\Livewire\Resident\Ipl\Index as ResidentIplIndex;
use App\Livewire\Resident\Ipl\Show as ResidentIplShow;
use App\Livewire\Resident\Profile as ResidentProfile;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');

    // Halaman promosi aplikasi, publik dan tanpa perlu masuk.
    Route::view('/info', 'marketing.info')->name('info');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Bukti bayar (BLOB database, maks 2 MB). Otorisasi dicek di controller via PaymentPolicy.
    Route::get('/payments/{payment}/proof', [PaymentProofController::class, 'show'])->name('payments.proof');

    // Level platform: hanya super_admin, dan sengaja berada di luar group
    // permission:access-admin karena isinya lintas seluruh condominan.
    Route::prefix('platform')->name('platform.')->middleware('role:super_admin')->group(function () {
        Route::get('/dashboard', PlatformDashboard::class)->name('dashboard');
    });

    Route::prefix('resident')->name('resident.')->group(function () {
        Route::get('/dashboard', ResidentDashboard::class)->name('dashboard');
        Route::get('/ipl', ResidentIplIndex::class)->name('ipl.index');
        Route::get('/ipl/{billing}', ResidentIplShow::class)->name('ipl.show');
        Route::get('/info', ResidentInfoIndex::class)->name('info.index');
        Route::get('/complaints', ResidentComplaintIndex::class)->name('complaints.index');
        Route::get('/complaints/{complaint}', ResidentComplaintShow::class)->name('complaints.show');
        Route::get('/forum', ResidentForumIndex::class)->name('forum.index');
        Route::get('/forum/{post}', ResidentForumShow::class)->name('forum.show');
        Route::get('/chess', ResidentChessIndex::class)->name('chess.index');
        Route::get('/chess/{game}', ResidentChessPlay::class)->name('chess.play');
        Route::get('/cash', ResidentCashIndex::class)->name('cash.index');
        Route::get('/pinjam', ResidentInventoryIndex::class)->name('inventory.index');
        Route::get('/profile', ResidentProfile::class)->name('profile');
    });

    Route::prefix('admin')->name('admin.')->middleware('permission:access-admin')->group(function () {
        Route::get('/dashboard', AdminDashboard::class)->name('dashboard')->middleware('permission:view-dashboard');
        Route::get('/housing-estates', EstateIndex::class)->name('estates.index')->middleware('permission:manage-houses');
        Route::get('/blocks', BlockIndex::class)->name('blocks.index')->middleware('permission:manage-houses');
        Route::get('/houses', HouseIndex::class)->name('houses.index')->middleware('permission:manage-houses');
        Route::get('/houses/create', HouseForm::class)->name('houses.create')->middleware('permission:manage-houses');
        Route::get('/houses/{house}/edit', HouseForm::class)->name('houses.edit')->middleware('permission:manage-houses');
        Route::get('/residents', ResidentIndex::class)->name('residents.index')->middleware('permission:manage-residents');
        Route::get('/residents/create', ResidentForm::class)->name('residents.create')->middleware('permission:manage-residents');
        Route::get('/residents/{resident}/edit', ResidentForm::class)->name('residents.edit')->middleware('permission:manage-residents');
        Route::get('/users', UserIndex::class)->name('users.index')->middleware('permission:manage-user');
        Route::get('/roles', RoleIndex::class)->name('roles.index')->middleware('permission:manage-role');

        Route::prefix('ipl')->name('ipl.')->group(function () {
            Route::get('/rates', IplRateIndex::class)->name('rates.index')->middleware('permission:manage-billing');
            Route::get('/generate', BillingGenerate::class)->name('generate')->middleware('permission:manage-billing');
            Route::get('/billings', BillingIndex::class)->name('billings.index')->middleware('permission:manage-billing,verify-payment');
            Route::get('/billings/{billing}', BillingShow::class)->name('billings.show')->middleware('permission:manage-billing,verify-payment');
            Route::get('/payments', PaymentIndex::class)->name('payments.index')->middleware('permission:manage-payment,verify-payment');
        });

        Route::prefix('water')->name('water.')->group(function () {
            Route::get('/rates', WaterRateIndex::class)->name('rates.index')->middleware('permission:manage-billing');
            Route::get('/readings', WaterReadings::class)->name('readings')->middleware('permission:manage-billing');
        });

        Route::prefix('cash')->name('cash.')->group(function () {
            Route::get('/accounts', CashAccountIndex::class)->name('accounts.index')->middleware('permission:manage-finance');
            Route::get('/transactions', CashTransactionIndex::class)->name('transactions.index')->middleware('permission:manage-finance');
        });

        Route::prefix('info')->name('info.')->group(function () {
            Route::get('/announcements', InfoAnnouncements::class)->name('announcements')->middleware('permission:manage-announcement');
            Route::get('/complaints', InfoComplaints::class)->name('complaints')->middleware('permission:manage-complaint');
            Route::get('/complaints/{complaint}', InfoComplaintDetail::class)->name('complaints.show')->middleware('permission:manage-complaint');
        });

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/items', InventoryItems::class)->name('items.index')->middleware('permission:manage-inventory');
            Route::get('/loans', InventoryLoans::class)->name('loans.index')->middleware('permission:manage-inventory');
        });

        Route::get('/forum', AdminForumIndex::class)->name('forum.index')->middleware('permission:manage-forum');

        Route::get('/activity-logs', ActivityLogIndex::class)->name('activity-logs.index')->middleware('permission:view-activity-log');
    });
});
