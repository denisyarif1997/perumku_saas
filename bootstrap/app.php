<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\TrackPageVisit;
use App\Support\AdminMenu;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'subscription' => EnsureSubscriptionActive::class,
        ]);

        // Hosting (cPanel/Nginx/Cloudflare) umumnya jalan di balik proxy:
        // tanpa ini Laravel mengira request masih http, lalu redirect
        // http <-> https berputar terus (gejala: ERR_TOO_MANY_REDIRECTS
        // yang "sembuh" sementara setelah hapus cookie).
        $middleware->trustProxies(at: '*');

        // Tamu yang kena middleware auth selalu ke /login.
        $middleware->redirectGuestsTo('/login');

        // User yang sudah login tapi buka /login atau /register:
        // arahkan sesuai hak akses, BUKAN ke "/" (yang me-redirect
        // balik ke /login -> loop guest -> login -> "/" -> /login ...).
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            if ($user?->hasRole('super_admin')) {
                return route('platform.dashboard');
            }

            if ($user?->hasPermission('access-admin')) {
                return AdminMenu::landingRouteFor($user)
                    ? route(AdminMenu::landingRouteFor($user))
                    : route('onboarding');
            }

            if ($user?->isResident()) {
                return route('resident.dashboard');
            }

            return route('onboarding');
        });

        // Pencatatan kunjungan halaman untuk statistik pemakaian aplikasi
        // di dashboard platform. appended (bukan prepend) supaya tidak
        // menambah query pada respons yang gagal.
        $middleware->web(append: [
            TrackPageVisit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
