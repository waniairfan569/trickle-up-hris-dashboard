<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyClockSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Carbon $date,
        public array $rows,
        public array $counts,
        public ?User $recipient = null,
    ) {}

    public function build()
    {
        return $this->subject('Daily clock-in / clock-out summary — ' . $this->date->format('l, d M Y'))
            ->view('emails.daily-clock-summary', [
                'date' => $this->date,
                'rows' => $this->rows,
                'counts' => $this->counts,
                'recipient' => $this->recipient,
            ]);
    }
}
