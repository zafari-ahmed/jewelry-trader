<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $company = \App\Models\Setting::get('general.company_name', 'Jewelry Trader');

        return new Envelope(subject: "{$company} · receipt for {$this->order->order_number}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.receipt', with: ['order' => $this->order]);
    }
}
