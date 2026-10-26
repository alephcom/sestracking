<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthLinkingTest extends TestCase
{
    use RefreshDatabase;

    private User $existing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->existing = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    private function fakeProviderUser(string $provider, string $email, array $raw = []): void
    {
        $socialUser = (new SocialiteUser)
            ->setRaw($raw)
            ->map(['id' => 'sub-123', 'name' => 'SSO Person', 'email' => $email, 'avatar' => null]);

        Socialite::shouldReceive('driver')->with($provider)->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialUser);
    }

    /** @test */
    public function google_verified_email_links_existing_account()
    {
        $this->fakeProviderUser('google', 'victim@example.com', ['email_verified' => true]);

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($this->existing);
        $this->assertSame('google', $this->existing->fresh()->provider);
    }

    /** @test */
    public function google_unverified_email_does_not_link_existing_account()
    {
        $this->fakeProviderUser('google', 'victim@example.com', ['email_verified' => false]);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($this->existing->fresh()->provider);
    }

    /** @test */
    public function microsoft_multi_tenant_email_does_not_link_existing_account()
    {
        config(['services.microsoft.tenant' => 'common']);
        $this->fakeProviderUser('microsoft', 'victim@example.com');

        $this->get('/auth/microsoft/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($this->existing->fresh()->provider);
    }

    /** @test */
    public function microsoft_single_tenant_email_links_existing_account()
    {
        config(['services.microsoft.tenant' => '11111111-2222-3333-4444-555555555555']);
        $this->fakeProviderUser('microsoft', 'victim@example.com');

        $this->get('/auth/microsoft/callback')->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($this->existing);
    }

    /** @test */
    public function unverified_email_does_not_auto_create_account()
    {
        config(['auth.socialite_auto_create_users' => true]);
        $this->fakeProviderUser('google', 'newcomer@example.com', ['email_verified' => false]);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'newcomer@example.com']);
    }

    /** @test */
    public function returning_sso_user_matches_by_provider_id_regardless_of_email()
    {
        $this->existing->update(['provider' => 'google', 'provider_id' => 'sub-123']);
        $this->fakeProviderUser('google', 'changed@example.com', ['email_verified' => false]);

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($this->existing);
    }
}
