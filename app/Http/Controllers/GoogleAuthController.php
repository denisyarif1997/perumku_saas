<?php

namespace App\Http\Controllers;

use App\Models\OAuthAccount;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleAuthController extends Controller
{
    public function redirect(GoogleAuthService $google): RedirectResponse
    {
        if (! $google->isConfigured()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Login Google belum dikonfigurasi. Hubungi administrator.',
            ]);
        }

        $authorization = $google->authorizationRequest();

        return redirect()->away($authorization['url']);
    }

    public function callback(Request $request, GoogleAuthService $google): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Login Google dibatalkan.',
            ]);
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $profile = $google->handleCallback($request->string('code')->toString(), $request->string('state')->toString());
        } catch (RuntimeException $e) {
            return redirect()->route('login')->withErrors(['email' => $e->getMessage()]);
        }

        // Opsi B: login-or-link saja, tanpa auto-signup. Email harus
        // terverifikasi Google sebelum boleh menaut ke akun mana pun.
        if (! $profile['email_verified']) {
            return redirect()->route('login')->withErrors([
                'email' => 'Email Google Anda belum terverifikasi.',
            ]);
        }

        $oauthAccount = OAuthAccount::query()
            ->where('provider', 'google')
            ->where('provider_id', $profile['google_id'])
            ->first();

        $user = $oauthAccount?->user;

        if (! $user) {
            $user = User::query()->where('email', $profile['email'])->first();
        }

        // OAuthAccount milik akun lain tidak boleh dipakai untuk masuk ke akun
        // berbeda (mis. klaim email Google berubah ke email orang lain).
        if ($oauthAccount && $oauthAccount->user_id && ! $oauthAccount->user) {
            $user = null;
        }

        if ($oauthAccount && $oauthAccount->user && $user && (int) $oauthAccount->user_id !== (int) $user->id) {
            $emailOwner = User::query()->where('email', $profile['email'])->first();

            if ($emailOwner && (int) $emailOwner->id !== (int) $oauthAccount->user_id) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Akun Google ini sudah tertaut ke akun lain. Hubungi administrator.',
                ]);
            }

            // Klaim email berubah tapi belum dipakai siapa pun → tetap login
            // sebagai pemilik bridge (identitas Google = sub, bukan email).
            $user = $oauthAccount->user;
        }

        // Email belum dikenal → arahkan ke pendaftaran perumahan baru
        // (onboarding), bukan auto-signup. Buat akun pending + bridge yatim
        // agar data Google tersimpan untuk dilengkapi di onboarding.
        if (! $user) {
            $pendingUser = DB::transaction(function () use ($profile, $oauthAccount) {
                $created = User::create([
                    'name' => mb_substr($profile['name'], 0, 100),
                    'email' => $profile['email'],
                    'password' => Str::random(40),
                    'avatar' => $profile['avatar'],
                    'status' => 'pending',
                    'email_verified_at' => now(),
                ]);

                $freshOAuth = $oauthAccount ?? OAuthAccount::query()
                    ->where('provider', 'google')
                    ->where('provider_id', $profile['google_id'])
                    ->first();

                if ($freshOAuth && ! $freshOAuth->user_id) {
                    $freshOAuth->forceFill([
                        'user_id' => $created->id,
                        'email' => $profile['email'],
                        'name' => $profile['name'],
                        'avatar' => $profile['avatar'] ?? null,
                        'access_token' => $profile['access_token'],
                        'refresh_token' => $profile['refresh_token'] ?? $freshOAuth->rawRefreshToken(),
                        'token_expires_at' => $profile['expires_at'],
                        'last_login_at' => now(),
                    ])->save();
                } elseif (! $freshOAuth) {
                    OAuthAccount::create([
                        'user_id' => $created->id,
                        'provider' => 'google',
                        'provider_id' => $profile['google_id'],
                        'email' => $profile['email'],
                        'name' => $profile['name'],
                        'avatar' => $profile['avatar'] ?? null,
                        'access_token' => $profile['access_token'],
                        'refresh_token' => $profile['refresh_token'],
                        'token_expires_at' => $profile['expires_at'],
                        'last_login_at' => now(),
                    ]);
                }

                return $created;
            });

            Auth::login($pendingUser);
            $request->session()->regenerate();

            return redirect()->intended(route('onboarding'));
        }

        // Pendaftar pending yang belum diverifikasi → lanjutkan onboarding.
        if ($user->status === 'pending') {
            $this->upsertOAuthAccount($user, $profile, $oauthAccount);

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('onboarding'));
        }

        if ($user->status !== 'active') {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda tidak aktif. Hubungi pengelola.',
            ]);
        }

        $this->upsertOAuthAccount($user, $profile, $oauthAccount);

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($profile['avatar'] && ! $user->avatar) {
            $user->forceFill(['avatar' => $profile['avatar']])->save();
        }

        if ($user->hasPermission('access-admin')) {
            return redirect()->intended(route('admin.dashboard'));
        }

        if ($user->isResident()) {
            return redirect()->intended(route('resident.dashboard'));
        }

        Auth::logout();
        $request->session()->invalidate();

        return redirect()->route('login')->withErrors([
            'email' => 'Akun Google Anda belum memiliki hak akses. Hubungi administrator.',
        ]);
    }

    /**
     * @param  array{google_id: string, email: string, name: string, avatar: string|null, access_token: string|null, refresh_token: string|null, expires_at: Carbon|null, email_verified: bool}  $profile
     */
    protected function upsertOAuthAccount(User $user, array $profile, ?OAuthAccount $oauthAccount = null): void
    {
        $oauthAccount ??= OAuthAccount::query()
            ->where('provider', 'google')
            ->where('provider_id', $profile['google_id'])
            ->first();

        // OAuthAccount milik user lain sudah ditolak di callback, jadi jangan timpa.
        if ($oauthAccount && $oauthAccount->user_id && (int) $oauthAccount->user_id !== (int) $user->id) {
            return;
        }

        OAuthAccount::updateOrCreate(
            ['provider' => 'google', 'provider_id' => $profile['google_id']],
            [
                'user_id' => $user->id,
                'email' => $profile['email'],
                'name' => $profile['name'],
                'avatar' => $profile['avatar'] ?? null,
                'access_token' => $profile['access_token'],
                'refresh_token' => $profile['refresh_token'] ?? $oauthAccount?->rawRefreshToken(),
                'token_expires_at' => $profile['expires_at'],
                'last_login_at' => now(),
            ]
        );

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
