<?php

namespace App\Exports;

use App\Models\CompanyWfhDay;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Excel sheet of the company-wide work-from-home days. */
class CompanyWfhDaysExport implements FromCollection, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return ['Date', 'Day', 'When', 'Note', 'Added by', 'Added on'];
    }

    public function collection()
    {
        return CompanyWfhDay::with('creator')->orderBy('date')->get()->map(function ($d) {
            $date = Carbon::parse($d->date);

            return [
                $date->format('Y-m-d'),
                $date->format('l'),
                $date->isToday() ? 'Today' : ($date->isFuture() ? 'Upcoming' : 'Past'),
                $d->note ?: '—',
                optional($d->creator)->full_name ?? '—',
                optional($d->created_at)->format('Y-m-d') ?? '—',
            ];
        });
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
