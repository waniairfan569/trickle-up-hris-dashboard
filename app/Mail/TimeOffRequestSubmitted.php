<?php

namespace App\Mail;

use App\Models\TimeOffRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TimeOffRequestSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public $timeOffRequest;

    /**
     * Create a new message instance.
     */
    public function __construct(TimeOffRequest $timeOffRequest)
    {
        $this->timeOffRequest = $timeOffRequest;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Time Off Request: ' . $this->timeOffRequest->employee->first_name . ' ' . $this->timeOffRequest->employee->last_name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.time-off.submitted',
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
