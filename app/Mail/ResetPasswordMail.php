<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $resetCode;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $resetCode)
    {
        $this->name = $name;
        $this->resetCode = $resetCode;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Password Reset Code')
                    ->view('emails.reset_password');
    }
}
