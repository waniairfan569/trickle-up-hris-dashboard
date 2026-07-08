<?php

namespace App\Mail;

use App\Models\TimeOffRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TimeOffRequestCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public $timeOffRequest;

    public function __construct(TimeOffRequest $timeOffRequest)
    {
        $this->timeOffRequest = $timeOffRequest;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Time Off Request Cancelled',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.time-off.cancelled',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
