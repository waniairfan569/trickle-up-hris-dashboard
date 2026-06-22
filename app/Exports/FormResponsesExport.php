<?php

namespace App\Exports;

use App\Models\CompanyForm;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FormResponsesExport implements FromCollection, WithHeadings, WithStyles
{
    private $inputFields;

    public function __construct(private CompanyForm $form)
    {
        $this->form->loadMissing('fields');
        $this->inputFields = $this->form->fields->filter(fn ($f) => $f->isInputField())->values();
    }

    public function headings(): array
    {
        $cols = ['Employee', 'Submitted At', 'Status'];
        foreach ($this->inputFields as $f) {
            $cols[] = $f->label;
        }

        return $cols;
    }

    public function collection()
    {
        $submissions = $this->form->submissions()->with(['employee', 'responses'])->get();

        return $submissions->map(function ($s) {
            $byKey = $s->responses->keyBy('field_key');
            $row = [
                optional($s->employee)->full_name ?? 'Unknown',
                optional($s->submitted_at)->format('Y-m-d H:i') ?? '—',
                ucfirst($s->status),
            ];
            foreach ($this->inputFields as $f) {
                $resp = $byKey->get($f->field_key);
                $row[] = $resp ? $resp->getDisplayValue() : '—';
            }

            return $row;
        });
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = chr(64 + min(26, 3 + $this->inputFields->count()));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B2D5E']],
        ]);

        return [];
    }
}
