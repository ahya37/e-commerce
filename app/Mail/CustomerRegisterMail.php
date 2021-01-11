<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Customer;

class CustomerRegisterMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    protected $customer;
    protected $randomPassword;

    // meminta data berupa informasi dan random password yang belum di encrypsi
    public function __construct(Customer $customer, $randomPassword)
    {
        $this->customer = $customer;
        $this->randomPassword = $randomPassword;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // mengset subject email, view mana yang akan di load dan data apa yang akan di passing ke view
        return $this->from('ahmadyaniid37@gmail.com')
                    ->subject('Verifikasi Pendaftaran Anda')
                    ->view('emails.register')
                    ->with([
                        'customer' => $this->customer,
                        'password' => $this->randomPassword
                    ]);
    }
}
