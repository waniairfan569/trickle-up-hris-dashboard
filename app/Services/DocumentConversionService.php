<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Converts uploaded Office documents (.doc/.docx) to PDF using a headless
 * LibreOffice binary, so they can be used in the field-placement builder.
 * Degrades gracefully: if LibreOffice isn't available on the host, toPdf()
 * returns null and the caller asks the user to upload a PDF instead.
 */
class DocumentConversionService
{
    /** Locate a LibreOffice/soffice binary, or null if none is available. */
    public function binary(): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $isWindows = stripos(PHP_OS, 'WIN') === 0;

        // 1) On PATH.
        foreach (['soffice', 'libreoffice'] as $name) {
            $lookup = $isWindows ? "where {$name} 2>NUL" : "command -v {$name} 2>/dev/null";
            $found = trim((string) @shell_exec($lookup));
            if ($found !== '') {
                return strtok($found, "\r\n"); // first line
            }
        }

        // 2) Common absolute install locations.
        $candidates = $isWindows
            ? [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            ]
            : [
                '/usr/bin/soffice', '/usr/bin/libreoffice',
                '/usr/local/bin/soffice', '/opt/libreoffice/program/soffice',
            ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // 3) Glob for versioned Linux installs (e.g. /opt/libreoffice7.6/program/soffice).
        if (!$isWindows) {
            $glob = glob('/opt/libreoffice*/program/soffice') ?: [];
            if (!empty($glob)) {
                return $glob[0];
            }
        }

        return null;
    }

    public function available(): bool
    {
        return $this->binary() !== null;
    }

    /**
     * Convert a stored Office file to PDF on the same disk. Returns the stored
     * path of the resulting PDF, or null if conversion isn't possible/failed.
     *
     * @param string $storedPath e.g. "tenants/1/company-documents/hr/x.docx"
     */
    public function toPdf(string $storedPath): ?string
    {
        $bin = $this->binary();
        if (!$bin) {
            return null;
        }

        $disk = Storage::disk();
        if (!$disk->exists($storedPath)) {
            return null;
        }

        // Copy the source into a private temp working dir on the local FS.
        $work = storage_path('app/tmp-convert/' . Str::random(14));
        if (!is_dir($work)) {
            @mkdir($work, 0775, true);
        }
        $inPath = $work . DIRECTORY_SEPARATOR . basename($storedPath);
        @file_put_contents($inPath, $disk->get($storedPath));

        // Run: soffice --headless --convert-to pdf --outdir <work> <input>
        $binQuoted = Str::contains($bin, ' ') ? '"' . $bin . '"' : $bin;
        $cmd = $binQuoted . ' --headless --norestore --nolockcheck --convert-to pdf --outdir '
            . escapeshellarg($work) . ' ' . escapeshellarg($inPath) . ' 2>&1';
        @shell_exec($cmd);

        $pdfLocal = preg_replace('/\.[^.\\/\\\\]+$/', '.pdf', $inPath);
        if (!is_file($pdfLocal) || filesize($pdfLocal) === 0) {
            $this->cleanup($work);
            return null;
        }

        // Store the PDF back on the disk beside the original.
        $pdfStored = preg_replace('/\.[^.\\/\\\\]+$/', '.pdf', $storedPath);
        if ($pdfStored === $storedPath) {
            $pdfStored .= '.pdf';
        }
        $disk->put($pdfStored, (string) file_get_contents($pdfLocal));

        $this->cleanup($work);

        return $disk->exists($pdfStored) ? $pdfStored : null;
    }

    private function cleanup(string $dir): void
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
