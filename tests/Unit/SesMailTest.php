<?php

namespace Tests\Unit;

use App\Mail\SesMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SesMailTest extends TestCase
{
    /** Messages captured by the array mailer configured in phpunit.xml. */
    private function sentMessages()
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }

    /** @test */
    public function configuration_set_header_is_added_to_the_sent_message()
    {
        Mail::to('someone@example.com')->send(new SesMail(
            subjectText: 'Hello',
            fromEmail: 'sender@example.com',
            fromName: 'Sender',
            data: ['message' => 'Body text'],
            configurationSet: 'tracking-set'
        ));

        $message = $this->sentMessages()->last()->getOriginalMessage();

        $this->assertSame('tracking-set', $message->getHeaders()->get('X-SES-CONFIGURATION-SET')?->getBodyAsString());
        $this->assertSame('Hello', $message->getSubject());
        $this->assertSame('sender@example.com', $message->getFrom()[0]->getAddress());
        $this->assertStringContainsString('Body text', $message->getHtmlBody());
    }

    /** @test */
    public function no_configuration_set_header_when_none_given()
    {
        Mail::to('someone@example.com')->send(new SesMail(
            subjectText: 'Hello',
            fromEmail: 'sender@example.com',
            data: ['message' => 'Body text'],
        ));

        $message = $this->sentMessages()->last()->getOriginalMessage();

        $this->assertFalse($message->getHeaders()->has('X-SES-CONFIGURATION-SET'));
    }
}
