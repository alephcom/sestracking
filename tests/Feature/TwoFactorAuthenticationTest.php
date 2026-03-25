<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_password_user_is_redirected_to_two_factor_setup_when_accessing_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'new@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'new@example.com',
            'password' => 'secret123',
            'submit' => true,
        ]);

        $this->assertAuthenticated();
        $this->get(route('dashboard.index'))
            ->assertRedirect(route('two-factor.setup'));
    }

    public function test_enrolled_user_gets_challenge_after_password_not_dashboard(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'two@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'two@example.com',
            'password' => 'secret123',
            'submit' => true,
        ]);

        $this->assertGuest();
        $this->assertTrue(session()->has('login.two_factor_pending_user_id'));
        $response = $this->get(route('two-factor.challenge'));
        $response->assertOk();
        $response->assertViewIs('auth.two-factor-challenge');
    }

    public function test_challenge_rejects_invalid_code(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'bad@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'bad@example.com',
            'password' => 'secret123',
            'submit' => true,
        ]);

        $this->post(route('two-factor.challenge.confirm'), [
            'code' => '000000',
        ]);

        $this->assertGuest();
    }

    public function test_challenge_accepts_valid_totp(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'good@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'good@example.com',
            'password' => 'secret123',
            'submit' => true,
        ]);

        $code = (new Google2FA)->getCurrentOtp((string) $user->fresh()->two_factor_secret);
        $response = $this->post(route('two-factor.challenge.confirm'), [
            'code' => $code,
        ]);

        $response->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_remember_me_is_applied_after_successful_challenge(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'remember@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'remember@example.com',
            'password' => 'secret123',
            'submit' => true,
            'remember' => '1',
        ]);

        $code = (new Google2FA)->getCurrentOtp((string) $user->fresh()->two_factor_secret);
        $this->post(route('two-factor.challenge.confirm'), [
            'code' => $code,
        ]);

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_sso_user_can_access_dashboard_without_two_factor(): void
    {
        $user = User::factory()->create([
            'email' => 'sso@example.com',
            'password' => Hash::make('secret123'),
            'provider' => 'google',
            'provider_id' => 'test-sub',
        ]);
        $this->assertFalse($user->requiresInAppTwoFactor());

        $this->actingAs($user);
        $this->get(route('dashboard.index'))->assertOk();
    }

    public function test_reset_two_factor_command_clears_enrollment(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'reset@example.com',
        ]);

        $this->assertTrue($user->fresh()->hasConfirmedTwoFactor());

        $exit = Artisan::call('user:reset-two-factor', [
            'identifier' => 'reset@example.com',
        ]);

        $this->assertSame(0, $exit);
        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_reset_two_factor_skips_sso_users(): void
    {
        $user = User::factory()->create([
            'provider' => 'google',
            'provider_id' => 'x',
        ]);

        $exit = Artisan::call('user:reset-two-factor', [
            'identifier' => $user->email,
        ]);

        $this->assertSame(1, $exit);
    }

    public function test_challenge_cancel_requires_post_and_clears_pending_session(): void
    {
        $user = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'cancel@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'cancel@example.com',
            'password' => 'secret123',
            'submit' => true,
        ]);

        $this->assertTrue(session()->has('login.two_factor_pending_user_id'));

        $this->post(route('two-factor.challenge.cancel'))->assertRedirect(route('login'));

        $this->assertFalse(session()->has('login.two_factor_pending_user_id'));
        $this->assertGuest();
    }
}
