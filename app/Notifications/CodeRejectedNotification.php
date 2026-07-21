<?php

namespace App\Notifications;

use App\Models\CodeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodeRejectedNotification extends Notification
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
        $reason = $this->codeRequest->rejection_reason;

        return [
            'type' => 'code_rejected',
            'urgent' => false,
            'title' => "🚫 Your {$tool} code request was declined",
            'message' => $reason ? ('Reason: ' . $reason) : 'HR was unable to share this code.',
            'reason' => $reason,
            'tool' => $tool,
            'url' => route('code-requests.my'),
            'icon' => 'x-circle',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your {$this->codeRequest->tool_name} code request was declined")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("Your request for a {$this->codeRequest->tool_name} code has been declined by HR.");

        if ($this->codeRequest->rejection_reason) {
            $mail->line('Reason: ' . $this->codeRequest->rejection_reason);
        }

        return $mail
            ->line('If you still need access, please reach out to HR or submit a new request.')
            ->action('View my requests', route('code-requests.my'));
    }
}
