<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    /** @test */
    public function get_request_with_credentials_does_not_log_in()
    {
        $this->get('/login?email=user@example.com&password=password123&submit=1')
            ->assertOk()
            ->assertViewIs('auth.login');

        $this->assertGuest();
        $this->assertFalse(session()->has('login.two_factor_pending_user_id'));
    }

    /** @test */
    public function repeated_failures_lock_out_the_email_even_with_the_right_password()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrong'])
                ->assertViewHas('error', 'Invalid email or password.');
        }

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'password123'])
            ->assertStatus(429);

        $this->assertGuest();
        $this->assertFalse(session()->has('login.two_factor_pending_user_id'));
    }

    /** @test */
    public function lockout_for_one_email_does_not_block_another()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'user@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'other@example.com', 'password' => 'wrong'])
            ->assertOk()
            ->assertViewHas('error', 'Invalid email or password.');
    }

    /** @test */
    public function per_ip_limit_applies_across_emails()
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/login', ['email' => "spray{$i}@example.com", 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'password123'])
            ->assertStatus(429);
    }

    /** @test */
    public function sso_linked_account_cannot_bypass_mfa_with_password()
    {
        $linked = User::factory()->create([
            'email' => 'linked@example.com',
            'password' => Hash::make('password123'),
            'provider' => 'google',
            'provider_id' => 'google-sub',
        ]);

        $this->post('/login', ['email' => $linked->email, 'password' => 'password123'])
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('This account signs in with Google');

        $this->assertGuest();
    }

    /** @test */
    public function logout_requires_post()
    {
        $this->actingAs(User::factory()->withTwoFactorEnrolled()->create());

        $this->get('/logout')->assertStatus(405);
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    /** @test */
    public function forgot_password_is_rate_limited_per_ip()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/forgot-password', ['email' => "someone{$i}@example.com"]);
        }

        $this->post('/forgot-password', ['email' => 'user@example.com'])->assertStatus(429);
    }
}
