<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export of code requests. Deliberately excludes the code VALUE — those
 * are redacted after use and must never leave the system in a report.
 */
class CodeRequestsExport implements FromCollection, WithHeadings, WithStyles
{
    /** @param Collection<int,\App\Models\CodeRequest> $requests */
    public function __construct(private Collection $requests)
    {
    }

    public function headings(): array
    {
        return ['Request #', 'Employee', 'Department', 'Tool', 'Message', 'Requested on', 'Status', 'Sent on', 'Handled by'];
    }

    public function collection()
    {
        return $this->requests->map(fn ($r) => [
            $r->request_number,
            optional($r->employee)->full_name ?? 'Unknown',
            optional(optional($r->employee)->department)->name ?? '—',
            $r->tool_name,
            $r->message,
            optional($r->created_at)->format('Y-m-d H:i') ?? '—',
            ucfirst(str_replace('_', ' ', (string) $r->status)),
            optional($r->code_sent_at)->format('Y-m-d H:i') ?? '—',
            optional($r->responder)->full_name ?? '—',
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B2D5E']],
        ]);

        return [];
    }
}
