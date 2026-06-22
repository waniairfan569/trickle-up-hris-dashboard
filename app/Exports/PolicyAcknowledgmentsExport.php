<?php

namespace App\Exports;

use App\Models\CompanyPolicy;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PolicyAcknowledgmentsExport implements FromCollection, WithHeadings, WithStyles
{
    public function __construct(private CompanyPolicy $policy)
    {
    }

    public function headings(): array
    {
        return ['Employee', 'Department', 'Status', 'Viewed At', 'Acknowledged At', 'Signature'];
    }

    public function collection()
    {
        return $this->policy->acknowledgments()->with('employee.department')->get()->map(fn ($a) => [
            optional($a->employee)->full_name ?? 'Unknown',
            optional(optional($a->employee)->department)->name ?? '—',
            ucfirst($a->status),
            optional($a->viewed_at)->format('Y-m-d H:i') ?? '—',
            optional($a->acknowledged_at)->format('Y-m-d H:i') ?? '—',
            $a->signature_type ? ucfirst($a->signature_type) . ($a->signature_name ? ' (' . $a->signature_name . ')' : '') : '—',
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B2D5E']],
        ]);

        return [];
    }
}
