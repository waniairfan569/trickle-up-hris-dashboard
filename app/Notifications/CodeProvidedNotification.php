<?php

namespace App\Notifications;

use App\Models\CodeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodeProvidedNotification extends Notification
{
    use Queueable;

    public function __construct(public CodeRequest $codeRequest) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        $tool = $this->codeRequest->tool_name;
        $note = $this->codeRequest->code_expires_note;

        return [
            'type' => 'code_provided',
            'urgent' => true,
            'title' => "🔑 Your {$tool} code is here!",
            'message' => 'Tap to view your login code.' . ($note ? ' ' . $note : ''),
            // Shown directly in the bell so the employee doesn't have to click away.
            'code' => $this->codeRequest->code_provided,
            'tool' => $tool,
            'note' => $note,
            'url' => route('code-requests.my'),
            'icon' => 'key-round',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("✅ Your {$this->codeRequest->tool_name} login code")
            ->view('emails.code-provided', ['codeRequest' => $this->codeRequest]);
    }
}
