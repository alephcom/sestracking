<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_notification_for_known_user(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'reset@example.com',
            'password' => Hash::make('old-password-9'),
            'super_admin' => false,
        ]);

        $response = $this->post('/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_reset_password_updates_password_and_redirects_to_login(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'reset2@example.com',
            'password' => Hash::make('old-password-9'),
            'super_admin' => false,
        ]);

        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'reset2@example.com',
            'password' => 'new-secure-pass-9',
            'password_confirmation' => 'new-secure-pass-9',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-pass-9', $user->password));
        $this->assertGuest();
    }

    public function test_reset_password_with_invalid_token_fails_validation(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'reset3@example.com',
            'password' => Hash::make('old-password-9'),
            'super_admin' => false,
        ]);

        $response = $this->post('/reset-password', [
            'token' => 'not-a-valid-token',
            'email' => 'reset3@example.com',
            'password' => 'new-secure-pass-9',
            'password_confirmation' => 'new-secure-pass-9',
        ]);

        $response->assertSessionHasErrors('email');
        $user->refresh();
        $this->assertTrue(Hash::check('old-password-9', $user->password));
    }

    public function test_forgot_password_validates_email(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_forgot_password_logs_link_request_with_outcome(): void
    {
        Notification::fake();
        Log::spy();

        User::create([
            'name' => 'Test User',
            'email' => 'logged@example.com',
            'password' => Hash::make('old-password-9'),
            'super_admin' => false,
        ]);

        $this->post('/forgot-password', [
            'email' => 'logged@example.com',
        ]);

        Log::shouldHaveReceived('log')->withArgs(function (string $level, string $message, array $context) {
            return $level === 'info'
                && $message === 'Password reset link requested.'
                && ($context['outcome'] ?? null) === 'reset_link_sent'
                && ($context['email'] ?? null) === 'logged@example.com';
        });
    }

    public function test_reset_password_failure_is_logged(): void
    {
        Log::spy();

        User::create([
            'name' => 'Test User',
            'email' => 'reset-log@example.com',
            'password' => Hash::make('old-password-9'),
            'super_admin' => false,
        ]);

        $this->post('/reset-password', [
            'token' => 'invalid-token',
            'email' => 'reset-log@example.com',
            'password' => 'new-secure-pass-9',
            'password_confirmation' => 'new-secure-pass-9',
        ]);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
            return $message === 'Password reset attempt failed.'
                && ($context['email'] ?? null) === 'reset-log@example.com'
                && isset($context['broker_status']);
        });
    }
}
