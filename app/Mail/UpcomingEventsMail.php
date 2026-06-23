<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class UpcomingEventsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $events,
        public Carbon $date,
        public ?User $recipient = null,
    ) {}

    public function build()
    {
        return $this->subject('Reminder: ' . $this->events->count() . ' event' . ($this->events->count() === 1 ? '' : 's') . ' tomorrow — ' . $this->date->format('D, d M'))
            ->view('emails.upcoming-events', [
                'events' => $this->events,
                'date' => $this->date,
                'recipient' => $this->recipient,
            ]);
    }
}
