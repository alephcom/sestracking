<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
