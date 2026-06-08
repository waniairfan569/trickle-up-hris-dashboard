<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employee;
    public $invitedBy;
    public $invitationUrl;
    public $expiresInHours;

    /**
     * Create a new message instance.
     */
    public function __construct(User $employee, User $invitedBy, string $invitationUrl, int $expiresInHours)
    {
        $this->employee = $employee;
        $this->invitedBy = $invitedBy;
        $this->invitationUrl = $invitationUrl;
        $this->expiresInHours = $expiresInHours;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to join ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.employee-invitation',
            with: [
                'companyName' => config('app.name'),
                'expiresAt' => now()->addHours($this->expiresInHours)->format('F j, Y, g:i a'),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
