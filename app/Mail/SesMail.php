<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Swift_Message;

class SesMail extends Mailable
{
    use Queueable, SerializesModels;


    public string $subjectText;
    public string $fromEmail;
    public string $fromName;
    public array $data;

    public ?string $configurationSet = null;

    /**
     * Create a new message instance.
     */
    public function __construct(
        string $subjectText, 
        string $fromEmail = 'info@aleph-com.net', 
        string $fromName = 'Aleph', 
        array $data = [], 
        string $configurationSet = '')
    {
        $this->subjectText = $subjectText;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->data = $data;
        $this->configurationSet = $configurationSet;
    }

   public function build(): static
    {
        $configurationSet = $this->configurationSet;
        return $this
            ->from(address: $this->fromEmail, name: $this->fromName)
            ->subject(subject: $this->subjectText)
            ->view(view: 'mail.sesmail')
            ->with(key: 'data', value: $this->data)
            ->withSwiftMessage(function (Swift_Message $message) use ($configurationSet) {
                if($configurationSet){$message->getHeaders()->addTextHeader('X-SES-CONFIGURATION-SET', $configurationSet);}
            });
    }

    /**
     * Get the message envelope.
     */
    // public function envelope(): Envelope
    // {
    //     return new Envelope(
    //         subject: 'Ses Mail',
    //     );
    // }

    /**
     * Get the message content definition.
     */
    // public function content(): Content
    // {
    //     return new Content(
    //         view: 'mail.sesmail',
    //     );
    // }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
