<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class RestaurantMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $date;
    protected $expense;
    protected $totalPax;

    /**
     * Create a new message instance.
     */
    public function __construct($date, $expense, $totalPax)
    {
        $this->date = $date;
        $this->expense = $expense;
        $this->totalPax = $totalPax;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Restaurant Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.restaurant',
            with: [
                'date' => Carbon::parse($this->date)->format('d-m-Y'),
                'expense' => $this->expense,
                'totalPax' => $this->totalPax,
            ],
        );
    }

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
