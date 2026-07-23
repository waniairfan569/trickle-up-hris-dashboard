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
    /**
     * Locate a LibreOffice/soffice binary, or null if none is available.
     *
     * IMPORTANT: on shared hosting (Plesk), PHP's open_basedir restriction makes
     * file functions like is_file()/glob() THROW when probing paths outside the
     * vhost (e.g. /usr/bin). So detection is done entirely through the shell
     * (command -v / test -x), which open_basedir does not restrict, and the whole
     * method is exception-proof — it degrades to null instead of a 500.
     */
    public function binary(): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        try {
            $isWindows = stripos(PHP_OS, 'WIN') === 0;

            // 1) On PATH (shell lookup — open_basedir-safe).
            foreach (['soffice', 'libreoffice'] as $name) {
                $lookup = $isWindows ? "where {$name} 2>NUL" : "command -v {$name} 2>/dev/null";
                $found = trim((string) @shell_exec($lookup));
                if ($found !== '') {
                    return strtok($found, "\r\n");
                }
            }

            if ($isWindows) {
                // Local dev only — no open_basedir here.
                foreach ([
                    'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                    'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                ] as $p) {
                    if (@is_file($p)) {
                        return $p;
                    }
                }
                return null;
            }

            // 2) Linux — probe common absolute locations via the shell, so
            //    open_basedir never bites (test -x, not PHP is_file()).
            $probe = @shell_exec(
                'for p in /usr/bin/soffice /usr/bin/libreoffice /usr/local/bin/soffice '
                . '/opt/libreoffice/program/soffice /opt/libreoffice*/program/soffice; '
                . 'do [ -x "$p" ] && echo "$p" && break; done 2>/dev/null'
            );
            $probe = trim((string) $probe);

            return $probe !== '' ? strtok($probe, "\r\n") : null;
        } catch (\Throwable $e) {
            return null; // never let detection throw
        }
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
        try {
            $bin = $this->binary();
            if (!$bin) {
                return null;
            }

            $disk = Storage::disk();
            if (!$disk->exists($storedPath)) {
                return null;
            }

            // Temp working dir INSIDE the app storage path (within open_basedir).
            $work = storage_path('app/tmp-convert/' . Str::random(14));
            if (!is_dir($work)) {
                @mkdir($work, 0775, true);
            }
            $inPath = $work . DIRECTORY_SEPARATOR . basename($storedPath);
            @file_put_contents($inPath, $disk->get($storedPath));

            // Run: soffice --headless --convert-to pdf --outdir <work> <input>
            // A dedicated UserInstallation profile dir (inside storage, so within
            // open_basedir) lets LibreOffice run as the web user even when HOME
            // isn't writable — a common silent-failure cause on shared hosting.
            $profileDir = $work . DIRECTORY_SEPARATOR . 'profile';
            @mkdir($profileDir, 0775, true);
            $profileUri = 'file:///' . ltrim(str_replace('\\', '/', $profileDir), '/');
            $isWindows = stripos(PHP_OS, 'WIN') === 0;
            $envPrefix = $isWindows ? '' : 'HOME=' . escapeshellarg($work) . ' ';

            $binQuoted = Str::contains($bin, ' ') ? '"' . $bin . '"' : $bin;
            $cmd = $envPrefix . $binQuoted . ' --headless --norestore --nolockcheck '
                . '-env:UserInstallation=' . escapeshellarg($profileUri)
                . ' --convert-to pdf --outdir '
                . escapeshellarg($work) . ' ' . escapeshellarg($inPath) . ' 2>&1';
            @shell_exec($cmd);

            $pdfLocal = preg_replace('/\.[^.\\/\\\\]+$/', '.pdf', $inPath);
            if (!@is_file($pdfLocal) || @filesize($pdfLocal) === 0) {
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
        } catch (\Throwable $e) {
            report($e);
            return null; // any failure → graceful fallback (upload a PDF)
        }
    }

    private function cleanup(string $dir): void
    {
        // Recursive — LibreOffice leaves a nested profile dir behind.
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            is_dir($f) ? $this->cleanup($f) : @unlink($f);
        }
        @rmdir($dir);
    }
}
