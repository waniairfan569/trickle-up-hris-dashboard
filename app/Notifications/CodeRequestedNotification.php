<?php

namespace App\Notifications;

use App\Models\CodeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public CodeRequest $codeRequest) {}

    /**
     * The work is done (code sent / rejected): clear this request's "needs a
     * login code" notification for every admin so it stops showing as unread.
     */
    public static function markResolved(int $codeRequestId): void
    {
        \Illuminate\Notifications\DatabaseNotification::whereNull('read_at')
            ->where('data->code_request_id', $codeRequestId)
            ->update(['read_at' => now()]);
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        $name = $this->codeRequest->employee->full_name ?? 'An employee';

        return [
            'type' => 'code_requested',
            'urgent' => true,
            'code_request_id' => $this->codeRequest->id,
            'title' => "{$name} needs a login code",
            'message' => "{$name} needs the {$this->codeRequest->tool_name} verification code. Check your email and share it ASAP.",
            'tool' => $this->codeRequest->tool_name,
            'url' => route('code-requests.pending'),
            'icon' => 'key-round',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $this->codeRequest->employee->full_name ?? 'An employee';
        $tool = $this->codeRequest->tool_name;

        $mail = (new MailMessage)
            ->subject("🔔 [{$name}] needs login code for {$tool}")
            ->greeting('Quick one 👋')
            ->line("**{$name}** needs the verification code for **{$tool}**.")
            ->line('The login code was likely sent to the company/HR email. Please check your inbox and share it ASAP.');

        if ($this->codeRequest->message) {
            $mail->line('Their note: "' . $this->codeRequest->message . '"');
        }

        return $mail->action('Send the code', route('code-requests.pending'))
            ->line('The faster you respond, the sooner they’re back to work.');
    }
}
