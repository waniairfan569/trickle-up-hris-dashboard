<?php

namespace App\Mail;

use App\Models\Candidate;
use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Candidate $candidate,
        public Job $job
    ) {}

    public function build()
    {
        $appId = 'APP-' . date('Y') . '-' . str_pad($this->candidate->id, 4, '0', STR_PAD_LEFT);

        return $this->subject("We received your application — {$this->job->title}")
            ->html("
                <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 24px;'>
                    <h2 style='color: #534AB7;'>Hi {$this->candidate->first_name}, 👋</h2>
                    <p>Thank you for applying for <strong>{$this->job->title}</strong> at Acme Corp. We've received your application and our team will review it shortly.</p>
                    <div style='background: #F7F7FD; border-radius: 12px; padding: 20px; margin: 24px 0;'>
                        <p style='margin: 0 0 8px; font-weight: 600; color: #534AB7;'>What happens next:</p>
                        <ol style='margin: 0; padding-left: 20px; color: #444; line-height: 2;'>
                            <li>Our team reviews your application (3–5 days)</li>
                            <li>If shortlisted, we'll invite you for a 30-minute phone screen</li>
                            <li>Technical or skills interview with the team</li>
                            <li>Final round + offer if successful</li>
                        </ol>
                    </div>
                    <p>Your application reference: <strong style='color: #534AB7;'>{$appId}</strong></p>
                    <p style='color: #888; font-size: 13px;'>Best regards,<br>The Acme Corp Hiring Team</p>
                </div>
            ");
    }
}
