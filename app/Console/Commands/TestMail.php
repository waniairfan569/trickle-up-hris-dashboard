<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    protected $signature = 'mail:test {email}';

    protected $description = 'Send a test email and report the active mail configuration + any error';

    public function handle(): int
    {
        $to = $this->argument('email');

        $this->line('');
        $this->info('Active mail configuration');
        $this->line('  MAILER : ' . config('mail.default'));
        $this->line('  HOST   : ' . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port'));
        $this->line('  SCHEME : ' . (config('mail.mailers.smtp.scheme') ?: '(auto)'));
        $this->line('  USER   : ' . config('mail.mailers.smtp.username'));
        $this->line('  FROM   : ' . config('mail.from.address') . ' (' . config('mail.from.name') . ')');
        $this->line('');

        if (config('mail.default') === 'log') {
            $this->warn('MAILER is "log" — no real email is sent. It is written to storage/logs/laravel.log.');
            $this->warn('Set MAIL_MAILER=smtp in .env, then run: php artisan config:clear');
        }

        try {
            Mail::raw('Test email from TrickleUp Hub sent at ' . now()->toDateTimeString(), function ($m) use ($to) {
                $m->to($to)->subject('TrickleUp Hub — mail test');
            });
            $this->info("OK: test email dispatched to {$to}.");
            $this->line('If you do not receive it, check the spam folder and confirm the SMTP credentials below are correct.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FAILED to send:');
            $this->error('  ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
