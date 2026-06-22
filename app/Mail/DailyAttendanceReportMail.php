<?php

namespace App\Mail;

use App\Exports\AttendanceReportExport;
use App\Models\AttendanceReportSettings;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class DailyAttendanceReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $reportData,
        public AttendanceReportSettings $settings,
        public ?User $recipient = null,
    ) {}

    public function build()
    {
        $subject = str_replace(
            '{{date}}',
            $this->reportData['date']->format('l, d M Y'),
            $this->settings->report_subject_template ?: 'Daily Attendance Report — {{date}}'
        );

        $mail = $this->subject($subject)->view('emails.daily-attendance-report', [
            'data' => $this->reportData,
            'settings' => $this->settings,
            'recipient' => $this->recipient,
        ]);

        if ($this->settings->attach_excel) {
            $filename = 'attendance-' . $this->reportData['date']->format('Y-m-d') . '.xlsx';
            $mail->attachData(
                Excel::raw(new AttendanceReportExport($this->reportData), \Maatwebsite\Excel\Excel::XLSX),
                $filename,
                ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            );
        }

        return $mail;
    }
}
