<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderPlacedMail extends Mailable
{
    public function __construct(
        public readonly Order $order,
        public readonly string $adminUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('orders.email.subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-placed');
    }
}
