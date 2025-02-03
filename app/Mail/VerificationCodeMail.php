<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $verificationCode;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $verificationCode)
    {
        $this->name = $name;
        $this->verificationCode = $verificationCode;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Account Verification Code')
                    ->view('emails.verification_code')
                    ->with([
                        'name' => $this->name,
                        'verificationCode' => $this->verificationCode,
                    ]);
    }
}
