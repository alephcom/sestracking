<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('current-password'),
        ]);
        $this->actingAs($this->user);
    }

    private function profileData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@example.com',
        ], $overrides);
    }

    /** @test */
    public function get_request_cannot_change_profile()
    {
        // A forged link/image tag must not be able to take over the account.
        $this->get('/edit_profile?'.http_build_query($this->profileData([
            'submit' => 1,
            'email' => 'attacker@example.com',
            'password' => 'attacker-password',
            'password_confirmation' => 'attacker-password',
        ])))->assertOk();

        $user = $this->user->fresh();
        $this->assertSame('owner@example.com', $user->email);
        $this->assertTrue(Hash::check('current-password', $user->password));
    }

    /** @test */
    public function changing_email_requires_current_password()
    {
        $this->post('/edit_profile', $this->profileData(['email' => 'new@example.com']))
            ->assertSessionHasErrors('current_password');

        $this->post('/edit_profile', $this->profileData([
            'email' => 'new@example.com',
            'current_password' => 'wrong-password',
        ]))->assertSessionHasErrors('current_password');

        $this->assertSame('owner@example.com', $this->user->fresh()->email);

        $this->post('/edit_profile', $this->profileData([
            'email' => 'new@example.com',
            'current_password' => 'current-password',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('new@example.com', $this->user->fresh()->email);
    }

    /** @test */
    public function changing_password_requires_current_password_and_min_length()
    {
        $this->post('/edit_profile', $this->profileData([
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]))->assertSessionHasErrors('current_password');

        $this->post('/edit_profile', $this->profileData([
            'password' => 'short',
            'password_confirmation' => 'short',
            'current_password' => 'current-password',
        ]))->assertSessionHasErrors('password');

        $this->post('/edit_profile', $this->profileData([
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
            'current_password' => 'current-password',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $this->user->fresh()->password));
    }

    /** @test */
    public function name_change_does_not_require_current_password()
    {
        $this->post('/edit_profile', $this->profileData(['name' => 'New Name']))
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $this->user->fresh()->name);
    }

    /** @test */
    public function cannot_take_another_users_email()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/edit_profile', $this->profileData([
            'email' => 'taken@example.com',
            'current_password' => 'current-password',
        ]))->assertSessionHasErrors('email');

        $this->assertSame('owner@example.com', $this->user->fresh()->email);
    }

    /** @test */
    public function sso_user_without_password_can_update_profile()
    {
        $ssoUser = User::factory()->withTwoFactorEnrolled()->create([
            'email' => 'sso@example.com',
            'password' => null,
            'provider' => 'google',
            'provider_id' => '123',
        ]);
        $this->actingAs($ssoUser);

        $this->post('/edit_profile', [
            'name' => 'SSO User',
            'username' => 'sso',
            'email' => 'sso@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame('SSO User', $ssoUser->fresh()->name);
    }
}
