<?php

namespace App\Services;

use App\Models\HrDocument;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Renders a filled HR document into a native, editable Word (.docx) file that
 * mirrors the PDF: branded header, navy section bands, a label/value grid,
 * checkboxes, tables and signature images.
 */
class HrDocumentWordService
{
    /** Temp PNGs written for embedded images, cleaned up after the writer saves. */
    private array $tempImages = [];

    private const NAVY  = '26324F';
    private const LABEL = 'EAF0FB';
    private const NOTE  = 'FDF6E3';
    private const LINE  = 'B9C2D6';

    /** Build the .docx and return its temporary file path. */
    public function build(HrDocument $document): string
    {
        $word = new PhpWord();
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);

        $section = $word->addSection([
            'pageSizeW'    => 11906, 'pageSizeH' => 16838,   // A4
            'marginTop'    => 1500, 'marginBottom' => 1300,
            'marginLeft'   => 1000, 'marginRight' => 1000,
            'headerHeight' => 700,  'footerHeight' => 700,
        ]);

        $this->addHeader($section);
        $this->addFooter($section);

        // Title
        $section->addText(
            mb_strtoupper($document->template_name),
            ['size' => 16, 'bold' => true, 'color' => self::NAVY],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 60]
        );
        if ($sub = optional($document->template)->subtitle) {
            $section->addText($sub, ['size' => 8, 'italic' => true, 'color' => '8A93A6'], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        }

        $data = $document->data ?? [];
        foreach ($document->schema as $sec) {
            $this->addSectionBand($section, $sec['title'] ?? 'Section');
            $table = $section->addTable([
                'borderSize'  => 6, 'borderColor' => self::LINE,
                'cellMargin'  => 70, 'width' => 5000, 'unit' => 'pct',
            ]);
            foreach ($sec['fields'] ?? [] as $field) {
                $this->addFieldRow($table, $field, $data[$field['id']] ?? null);
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'hrdoc') . '.docx';
        IOFactory::createWriter($word, 'Word2007')->save($tmp);

        foreach ($this->tempImages as $img) {
            @unlink($img);
        }
        $this->tempImages = [];

        return $tmp;
    }

    // ── layout helpers ─────────────────────────────────────────────

    private function addHeader($section): void
    {
        $header = $section->addHeader();
        $table = $header->addTable(['width' => 5000, 'unit' => 'pct']);
        $table->addRow();
        $table->addCell(6000, ['valign' => 'center'])
            ->addText('TRICKLE ûP', ['bold' => true, 'size' => 15, 'color' => self::NAVY]);
        $logoCell = $table->addCell(4000, ['valign' => 'center']);
        $logo = public_path('images/logo.png');
        if (is_file($logo)) {
            $logoCell->addImage($logo, ['height' => 34, 'alignment' => Jc::END]);
        }
        $header->addText('PRIVATE LIMITED', ['size' => 7, 'color' => '8A93A6'], ['alignment' => Jc::END, 'spaceAfter' => 0]);
    }

    private function addFooter($section): void
    {
        $footer = $section->addFooter();
        $footer->addText(
            'Plot 50, Business Bay, Phase 7 Sector F, Bahria Town, Rawalpindi  ·  hello@trickleup.co.uk  ·  www.trickleup.co.uk',
            ['size' => 7, 'color' => '8A93A6'],
            ['alignment' => Jc::CENTER]
        );
    }

    private function addSectionBand($section, string $title): void
    {
        $t = $section->addTable(['width' => 5000, 'unit' => 'pct', 'cellMargin' => 60]);
        $t->addRow();
        $t->addCell(5000, ['bgColor' => self::NAVY])
            ->addText(mb_strtoupper($title), ['bold' => true, 'size' => 9, 'color' => 'FFFFFF'], ['spaceBefore' => 40, 'spaceAfter' => 40]);
    }

    private function addFieldRow($table, array $field, $value): void
    {
        $type = $field['type'] ?? 'text';

        // Full-width note band
        if ($type === 'note') {
            $table->addRow();
            $table->addCell(10000, ['gridSpan' => 2, 'bgColor' => self::NOTE])
                ->addText($field['text'] ?? '', ['size' => 8, 'color' => '7A5B12'], ['spaceBefore' => 20, 'spaceAfter' => 20]);
            return;
        }

        $table->addRow();
        $table->addCell(3000, ['bgColor' => self::LABEL, 'valign' => 'top'])
            ->addText($field['label'] ?? '', ['bold' => true, 'size' => 9, 'color' => self::NAVY]);
        $cell = $table->addCell(7000, ['valign' => 'top']);

        switch ($type) {
            case 'checkbox':
            case 'radio':
                $line = '';
                foreach ($field['options'] ?? [] as $opt) {
                    $on = $type === 'checkbox'
                        ? (is_array($value) && in_array($opt, $value, true))
                        : ($value === $opt);
                    $line .= ($on ? '☒' : '☐') . ' ' . $opt . '     ';
                }
                $cell->addText(trim($line), ['size' => 9]);
                break;

            case 'table':
                $inner = $cell->addTable(['borderSize' => 4, 'borderColor' => 'CFD8E8', 'cellMargin' => 50, 'width' => 5000, 'unit' => 'pct']);
                $inner->addRow();
                foreach ($field['columns'] ?? [] as $col) {
                    $inner->addCell(2000, ['bgColor' => self::NAVY])->addText($col, ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
                }
                foreach ((is_array($value) ? $value : []) as $row) {
                    $inner->addRow();
                    foreach ($field['columns'] ?? [] as $col) {
                        $inner->addCell(2000)->addText((string) ($row[$col] ?? ''), ['size' => 8]);
                    }
                }
                if (empty($value)) {
                    $inner->addRow();
                    $inner->addCell(2000, ['gridSpan' => max(1, count($field['columns'] ?? []))])->addText('—', ['size' => 8, 'color' => '9AA4B8']);
                }
                break;

            case 'signature':
                if (is_array($value) && ! empty($value['image']) && ($png = $this->dataUriToTemp($value['image']))) {
                    $cell->addImage($png, ['height' => 44]);
                } else {
                    $cell->addText('__________________________', ['size' => 11, 'color' => self::NAVY]);
                }
                $meta = is_array($value) ? trim(($value['name'] ?? '') . (empty($value['date']) ? '' : '   ·   ' . $this->fmtDate($value['date']))) : '';
                if ($meta !== '') {
                    $cell->addText($meta, ['size' => 8, 'color' => '64748B']);
                }
                break;

            case 'textarea':
                foreach (preg_split('/\r\n|\r|\n/', (string) $value) as $ln) {
                    $cell->addText($ln, ['size' => 9]);
                }
                break;

            case 'date':
                $cell->addText($this->fmtDate($value), ['size' => 9]);
                break;

            default:
                $cell->addText(is_array($value) ? implode(', ', $value) : (string) $value, ['size' => 9]);
        }
    }

    private function dataUriToTemp(string $dataUri): ?string
    {
        if (! preg_match('/^data:image\/(png|jpe?g);base64,(.+)$/s', $dataUri, $m)) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false) {
            return null;
        }
        $path = tempnam(sys_get_temp_dir(), 'sig') . '.' . ($m[1] === 'jpg' ? 'jpeg' : $m[1]);
        file_put_contents($path, $bin);
        $this->tempImages[] = $path;

        return $path;
    }

    private function fmtDate($v): string
    {
        if (! $v) {
            return '';
        }
        try {
            return Carbon::parse($v)->format('d M Y');
        } catch (\Throwable $e) {
            return (string) $v;
        }
    }
}
