<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $messageContent;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $messageContent)
    {
        $this->name = $name;
        $this->messageContent = $messageContent;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Response to Your Inquiry')
                    ->view('emails.contact_response');
    }
}
