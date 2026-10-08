<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleAuthService
{
    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function isConfigured(): bool
    {
        return (string) config('services.google.client_id') !== ''
            && (string) config('services.google.client_secret') !== ''
            && (string) config('services.google.redirect') !== '';
    }

    /**
     * @return array{url: string, state: string, code_verifier: string}
     */
    public function authorizationRequest(): array
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(96);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        session()->put('google_oauth', [
            'state' => $state,
            'code_verifier' => $codeVerifier,
        ]);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
            'prompt' => 'select_account',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return [
            'url' => self::AUTH_URL.'?'.$query,
            'state' => $state,
            'code_verifier' => $codeVerifier,
        ];
    }

    /**
     * @return array{google_id: string, email: string, name: string, avatar: string|null, access_token: string|null, refresh_token: string|null, expires_at: Carbon|null, email_verified: bool}
     */
    public function handleCallback(string $code, string $state): array
    {
        $stored = session()->pull('google_oauth');

        if (! is_array($stored) || ($stored['state'] ?? null) !== $state || ($stored['code_verifier'] ?? '') === '') {
            throw new RuntimeException('Sesi login Google tidak valid atau sudah kedaluwarsa.');
        }

        $tokenResponse = Http::timeout(10)->retry(1, 200)->asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
            'code_verifier' => $stored['code_verifier'],
        ]);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('Gagal menukar kode otorisasi Google.');
        }

        $tokens = $tokenResponse->json();
        $accessToken = (string) ($tokens['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('Token akses Google tidak diterima.');
        }

        $profileResponse = Http::timeout(10)->retry(1, 200)->withToken($accessToken)->get(self::USERINFO_URL);

        if (! $profileResponse->successful()) {
            throw new RuntimeException('Gagal membaca profil Google.');
        }

        $profile = $profileResponse->json();
        $googleId = (string) ($profile['sub'] ?? '');
        $email = strtolower(trim((string) ($profile['email'] ?? '')));

        if ($googleId === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Data profil Google tidak lengkap.');
        }

        $expiresIn = (int) ($tokens['expires_in'] ?? 0);

        return [
            'google_id' => $googleId,
            'email' => $email,
            'name' => trim((string) ($profile['name'] ?? '')) ?: explode('@', $email)[0],
            'avatar' => isset($profile['picture']) && is_string($profile['picture']) ? $profile['picture'] : null,
            'access_token' => $accessToken,
            'refresh_token' => isset($tokens['refresh_token']) ? (string) $tokens['refresh_token'] : null,
            'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'email_verified' => filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
