<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class HotelMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $date;
    protected $expense;
    protected $totalPax;
    protected $attachmentPaths = [];

    /**
     * Create a new message instance.
     */
    public function __construct($subject, $date, $expense, $totalPax, $attachmentPaths = [])
    {
        $this->subject = $subject;
        $this->date = $date;
        $this->expense = $expense;
        $this->totalPax = $totalPax;
        $this->attachmentPaths = $attachmentPaths;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.hotel',
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
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $result = [];
        foreach ($this->attachmentPaths as $attachmentPath) {
            $result[] = Attachment::fromPath($attachmentPath);
        }
        return $result;
    }
}
