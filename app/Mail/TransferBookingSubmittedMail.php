<?php

namespace App\Mail;

use App\Models\TransferBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransferBookingSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TransferBooking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your transfer request has been received — '.$this->booking->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.transfer-booking-submitted',
            with: ['booking' => $this->booking->loadMissing(['legs.transportClass', 'extras'])],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
