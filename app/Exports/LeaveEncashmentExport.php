<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export of leave-encashment records for a renewal year (respecting the
 * admin list's status/policy filters). Downloaded from the Leave Encashment page.
 */
class LeaveEncashmentExport implements FromCollection, WithHeadings, WithStyles
{
    /** @param Collection<int,\App\Models\LeaveEncashmentRecord> $records */
    public function __construct(private Collection $records)
    {
    }

    public function headings(): array
    {
        return [
            'Employee', 'Department', 'Leave Type', 'Leave Year', 'Year',
            'Days Remaining', 'Days Encashed', 'Days Lapsed',
            'Daily Rate', 'Amount', 'Currency', 'Status',
            'Processed By', 'Processed At', 'Payment Date', 'Reference',
        ];
    }

    public function collection()
    {
        return $this->records->map(fn ($r) => [
            optional($r->employee)->full_name ?? 'Unknown',
            optional(optional($r->employee)->department)->name ?? '—',
            optional($r->policy)->name ?? '—',
            $r->leave_year_label ?? '—',
            $r->renewal_year,
            (float) $r->days_remaining_before_renewal,
            (float) $r->days_to_encash,
            (float) $r->days_lapsed,
            (float) $r->daily_rate,
            (float) $r->encashment_amount,
            $r->currency,
            ucfirst((string) $r->status),
            optional($r->processedBy)->full_name ?? '—',
            optional($r->processed_at)->format('Y-m-d H:i') ?? '—',
            optional($r->payment_date)->format('Y-m-d') ?? '—',
            $r->payment_reference ?? '—',
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B2D5E']],
        ]);

        return [];
    }
}
