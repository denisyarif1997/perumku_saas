<?php

namespace Tests\Feature;

use App\Models\OAuthAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_requires_configuration(): void
    {
        config()->set('services.google.client_id', null);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect(route('login'));
    }

    public function test_google_redirect_points_to_google(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
        config()->set('services.google.redirect', 'http://localhost:8000/auth/google/callback');

        $this->get(route('auth.google.redirect'))
            ->assertRedirect()
            ->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');

        $this->assertNotEmpty(session('google_oauth.state'));
    }

    public function test_google_callback_logs_in_existing_user_and_stores_bridge(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
        config()->set('services.google.redirect', 'http://localhost:8000/auth/google/callback');

        $this->seed();
        $roleId = Role::where('slug', 'resident')->value('id');
        $user = User::factory()->create([
            'email' => 'warga@example.com',
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        session()->put('google_oauth', ['state' => 'valid-state', 'code_verifier' => 'verifier']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-123',
                'email' => 'warga@example.com',
                'email_verified' => true,
                'name' => 'Warga Google',
                'picture' => 'https://example.com/avatar.jpg',
            ]),
        ]);

        $this->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect(route('resident.dashboard'));

        $this->assertAuthenticatedAs($user->fresh());

        $this->assertDatabaseHas('oauth_accounts', [
            'provider' => 'google',
            'provider_id' => 'google-123',
            'user_id' => $user->id,
            'email' => 'warga@example.com',
        ]);
    }

    public function test_google_callback_creates_pending_account_and_redirects_to_onboarding(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
        config()->set('services.google.redirect', 'http://localhost:8000/auth/google/callback');

        $this->seed();

        session()->put('google_oauth', ['state' => 'valid-state', 'code_verifier' => 'verifier']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-unknown',
                'email' => 'orang-asing@example.com',
                'email_verified' => true,
                'name' => 'Orang Asing',
            ]),
        ]);

        $this->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect(route('onboarding'));

        $user = User::where('email', 'orang-asing@example.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->housing_estate_id);
        $this->assertAuthenticatedAs($user->fresh());

        $this->assertDatabaseHas('oauth_accounts', [
            'provider' => 'google',
            'provider_id' => 'google-unknown',
            'user_id' => $user->id,
        ]);

        // Belum ada inject ke tabel produksi sebelum superadmin approve.
        $this->assertDatabaseMissing('housing_estates', ['email' => 'orang-asing@example.com']);
    }

    public function test_google_callback_rejects_unverified_email(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
        config()->set('services.google.redirect', 'http://localhost:8000/auth/google/callback');

        $this->seed();
        $roleId = Role::where('slug', 'resident')->value('id');
        User::factory()->create([
            'email' => 'warga@example.com',
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        session()->put('google_oauth', ['state' => 'valid-state', 'code_verifier' => 'verifier']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-123',
                'email' => 'warga@example.com',
                'email_verified' => false,
                'name' => 'Warga Google',
            ]),
        ]);

        $this->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('oauth_accounts', ['provider_id' => 'google-123']);
    }

    public function test_google_callback_rejects_bridge_owned_by_another_user(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
        config()->set('services.google.redirect', 'http://localhost:8000/auth/google/callback');

        $this->seed();
        $roleId = Role::where('slug', 'resident')->value('id');
        $owner = User::factory()->create([
            'email' => 'pemilik@example.com',
            'role_id' => $roleId,
            'status' => 'active',
        ]);
        User::factory()->create([
            'email' => 'korban@example.com',
            'role_id' => $roleId,
            'status' => 'active',
        ]);
        OAuthAccount::create([
            'user_id' => $owner->id,
            'provider' => 'google',
            'provider_id' => 'google-123',
            'email' => 'pemilik@example.com',
            'name' => 'Pemilik',
        ]);

        session()->put('google_oauth', ['state' => 'valid-state', 'code_verifier' => 'verifier']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-123',
                'email' => 'korban@example.com',
                'email_verified' => true,
                'name' => 'Pemilik',
            ]),
        ]);

        // Identitas Google (sub google-123) milik pemilik → tetap login
        // sebagai pemilik meski klaim email berubah, anti take-over.
        $this->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect(route('resident.dashboard'));

        $this->assertAuthenticatedAs($owner->fresh());
        $this->assertDatabaseHas('oauth_accounts', [
            'provider' => 'google',
            'provider_id' => 'google-123',
            'user_id' => $owner->id,
        ]);
    }

    public function test_google_callback_handles_userinfo_timeout_gracefully(): void
    {
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
        config()->set('services.google.redirect', 'http://localhost:8000/auth/google/callback');

        session()->put('google_oauth', ['state' => 'valid-state', 'code_verifier' => 'verifier']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::failedConnection(),
        ]);

        $this->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'valid-state']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('oauth_accounts', ['provider' => 'google']);
    }

    public function test_google_callback_rejects_invalid_state(): void
    {
        app(GoogleAuthService::class);

        $this->get(route('auth.google.callback', ['code' => 'auth-code', 'state' => 'wrong-state']))
            ->assertRedirect(route('login'));
    }

    public function test_oauth_account_model_links_user(): void
    {
        $bridge = new OAuthAccount([
            'provider' => 'google',
            'provider_id' => 'google-1',
            'email' => 'a@example.com',
        ]);

        $this->assertSame('oauth_accounts', $bridge->getTable());
        $this->assertInstanceOf(User::class, $bridge->user()->getRelated());
    }
}
