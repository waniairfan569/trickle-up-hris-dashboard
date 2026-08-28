<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Excel export of equipment requests for a date range / status selection. */
class EquipmentRequestsExport implements FromCollection, WithHeadings, WithStyles
{
    /** @param Collection<int,\App\Models\EquipmentRequest> $requests */
    public function __construct(private Collection $requests)
    {
    }

    public function headings(): array
    {
        return ['Request #', 'Employee', 'Department', 'Equipment', 'Reason', 'Requested on', 'Expected return', 'Status', 'Reviewed by', 'Reviewed on', 'Note'];
    }

    public function collection()
    {
        return $this->requests->map(fn ($r) => [
            $r->request_number,
            optional($r->employee)->full_name ?? 'Unknown',
            optional(optional($r->employee)->department)->name ?? '—',
            $r->equipment_name,
            $r->reason,
            optional($r->created_at)->format('Y-m-d H:i') ?? '—',
            optional($r->expected_return_date)->format('Y-m-d') ?? '—',
            ucfirst($r->status),
            optional($r->reviewer)->full_name ?? '—',
            optional($r->reviewed_at)->format('Y-m-d H:i') ?? '—',
            $r->review_note ?: '—',
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B2D5E']],
        ]);

        return [];
    }
}
