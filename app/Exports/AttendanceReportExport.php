<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(private array $reportData)
    {
    }

    public function title(): string
    {
        return 'Attendance ' . $this->reportData['date']->format('Y-m-d');
    }

    public function headings(): array
    {
        return ['Employee', 'Department', 'Clock In', 'Clock Out', 'Hours Worked', 'Late (min)', 'Overtime (min)', 'Status'];
    }

    public function collection()
    {
        return collect($this->reportData['full_table'])->map(fn ($r) => [
            $r['name'],
            $r['department'] ?? '—',
            $r['clock_in'],
            $r['clock_out'],
            $r['hours_worked'],
            $r['late_minutes'],
            $r['overtime_minutes'],
            $r['status_label'],
        ]);
    }

    public function columnWidths(): array
    {
        return ['A' => 26, 'B' => 20, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 12, 'G' => 14, 'H' => 18];
    }

    public function styles(Worksheet $sheet)
    {
        // Header row — dark blue background, white bold text.
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B2D5E']],
        ]);

        // Tint late/absent rows.
        $rows = $this->reportData['full_table'];
        foreach ($rows as $i => $r) {
            $rowNum = $i + 2; // +1 header, +1 1-based
            $color = match ($r['status']) {
                'late' => 'FFF3CD',
                'absent', 'missing_clock_out' => 'FFE5E5',
                default => null,
            };
            if ($color) {
                $sheet->getStyle("A{$rowNum}:H{$rowNum}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                ]);
            }
        }

        return [];
    }
}
