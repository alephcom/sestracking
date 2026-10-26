<?php

namespace Tests\Feature;

use App\Mail\SesMail;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function testMail(): array
    {
        return [
            'sendFrom' => 'ceo@example.com',
            'sendTo' => 'someone@example.com',
            'subject' => 'Hello',
            'message' => 'Test body',
        ];
    }

    /** @test */
    public function regular_user_cannot_send_test_mail()
    {
        Mail::fake();
        $this->actingAs(User::factory()->withTwoFactorEnrolled()->create(['super_admin' => false]));

        $this->get('/send_test')->assertForbidden();
        $this->post('/send_test/send', $this->testMail())->assertForbidden();

        Mail::assertNothingSent();
    }

    /** @test */
    public function project_admin_cannot_send_test_mail()
    {
        Mail::fake();
        $admin = User::factory()->withTwoFactorEnrolled()->create(['super_admin' => false]);
        Project::factory()->create()->users()->attach($admin->id, ['role' => 'admin']);
        $this->actingAs($admin);

        $this->post('/send_test/send', $this->testMail())->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertDontSee('Send Test Mail');

        Mail::assertNothingSent();
    }

    /** @test */
    public function super_admin_can_send_test_mail()
    {
        Mail::fake();
        config(['mail.from.name' => 'CloudVeil']);
        $this->actingAs(User::factory()->withTwoFactorEnrolled()->create(['super_admin' => true]));

        $this->get('/dashboard')->assertOk()->assertSee('Send Test Mail');
        $this->post('/send_test/send', $this->testMail())->assertRedirect();

        Mail::assertSent(SesMail::class, fn (SesMail $mail) => $mail->hasTo('someone@example.com')
            && $mail->fromName === 'CloudVeil');
    }
}
