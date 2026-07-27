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
     * Font families a .docx references (from word/fontTable.xml + theme). These
     * are the fonts the layout was DESIGNED with; if the converting host lacks
     * them, LibreOffice substitutes different-width fonts and the PDF layout
     * drifts. Returns [] for non-docx / unreadable / no-zip-extension.
     */
    public function fontsInDocx(string $storedPath): array
    {
        try {
            if (!class_exists(\ZipArchive::class) || !Str::endsWith(strtolower($storedPath), '.docx')) {
                return [];
            }
            $disk = Storage::disk();
            if (!$disk->exists($storedPath)) {
                return [];
            }

            // ZipArchive needs a real local file (the disk may be remote).
            $tmp = tempnam(sys_get_temp_dir(), 'ft') . '.docx';
            @file_put_contents($tmp, $disk->get($storedPath));

            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                @unlink($tmp);
                return [];
            }

            $names = [];
            foreach ([
                'word/fontTable.xml' => '/w:name="([^"]+)"/',
                'word/theme/theme1.xml' => '/typeface="([^"]+)"/',
            ] as $entry => $rx) {
                $xml = $zip->getFromName($entry);
                if ($xml === false) {
                    continue;
                }
                if (preg_match_all($rx, $xml, $m)) {
                    foreach ($m[1] as $n) {
                        $n = trim($n);
                        if ($n !== '' && $n[0] !== '+') { // skip theme refs like "+mn-lt"
                            $names[$n] = true;
                        }
                    }
                }
            }
            $zip->close();
            @unlink($tmp);

            return array_keys($names);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Concatenated text of every <w:t> run in document order, plus a map of
     * [node, startOffset, length] windows so an absolute char offset can be
     * resolved back to the run node that holds it.
     *
     * @return array{0:string,1:array<int,array{0:\DOMNode,1:int,2:int}>}
     */
    private function buildRunTextMap(\DOMXPath $xpath): array
    {
        $full = '';
        $map = [];
        foreach ($xpath->query('//w:t') as $t) {
            $len = mb_strlen($t->textContent);
            $map[] = [$t, mb_strlen($full), $len];
            $full .= $t->textContent;
        }

        return [$full, $map];
    }

    /**
     * Insert tokens the admin added in the browser editor into a COPY of the
     * original .docx, so "convert as-is" keeps the Word branding/layout AND the
     * tokens. Each entry is ['anchor' => text just before the caret at insert
     * time, 'token' => '[Employee Name]']; the token is placed right after the
     * anchor text within the same run (so it inherits that run's formatting).
     * Tokens are expected in document order; a forward cursor keeps them from
     * clustering. Returns the stored path of the injected copy, or null if
     * nothing could be placed (caller then converts the untouched original).
     */
    public function injectTokensIntoDocx(string $storedDocxPath, array $tokens): ?string
    {
        try {
            if (!class_exists(\ZipArchive::class) || empty($tokens)
                || !Str::endsWith(strtolower($storedDocxPath), '.docx')) {
                return null;
            }
            $disk = Storage::disk();
            if (!$disk->exists($storedDocxPath)) {
                return null;
            }

            $work = tempnam(sys_get_temp_dir(), 'inj') . '.docx';
            @file_put_contents($work, $disk->get($storedDocxPath));

            $zip = new \ZipArchive();
            if ($zip->open($work) !== true) {
                @unlink($work);
                return null;
            }
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                $zip->close();
                @unlink($work);
                return null;
            }

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            libxml_use_internal_errors(true);
            $ok = $dom->loadXML($xml);
            libxml_clear_errors();
            if (!$ok) {
                $zip->close();
                @unlink($work);
                return null;
            }

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            // Normalise anchors: drop trailing blank/underscore fill so an anchor
            // like "Signature: _____" becomes "Signature:" (a real, findable
            // label). Anchors shorter than 3 meaningful chars are too generic to
            // place safely, so they're skipped rather than dumped at the top.
            $pending = [];
            foreach ($tokens as $tk) {
                $anchor = rtrim((string) ($tk['anchor'] ?? ''), " \t\r\n_.–—-");
                if (empty($tk['token']) || mb_strlen(trim($anchor)) < 3) {
                    continue;
                }
                $pending[] = ['anchor' => $anchor, 'token' => $tk['token']];
            }

            $placed = 0;
            // A single forward cursor over the WHOLE document, so tokens land in
            // reading order and two tokens can never pile up at the same match
            // (each search starts after the previous insertion).
            $cursor = 0;

            foreach ($pending as $tk) {
                [$full, $map] = $this->buildRunTextMap($xpath);

                // Prefer the next occurrence at/after the cursor; only fall back
                // to a from-start search if the anchor doesn't appear ahead.
                $pos = mb_strpos($full, $tk['anchor'], $cursor);
                if ($pos === false) {
                    $pos = mb_strpos($full, $tk['anchor']);
                }
                if ($pos === false) {
                    continue;
                }
                $end = $pos + mb_strlen($tk['anchor']);

                foreach ($map as [$node, $start, $len]) {
                    if ($end >= $start && $end <= $start + $len) {
                        $local = $end - $start;
                        $text = $node->textContent;
                        $before = mb_substr($text, 0, $local);
                        $after = mb_substr($text, $local);
                        $sep = ($before !== '' && !preg_match('/\s$/u', $before)) ? ' ' : '';
                        $node->textContent = $before . $sep . $tk['token'] . $after;
                        $node->setAttribute('xml:space', 'preserve');
                        $placed++;
                        // Advance past what we just inserted so the next token
                        // searches forward from here.
                        $cursor = $end + mb_strlen($sep . $tk['token']);
                        break;
                    }
                }
            }

            if ($placed === 0) {
                $zip->close();
                @unlink($work);
                return null;
            }

            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $dom->saveXML());
            $zip->close();

            $stored = preg_replace('/\.docx$/i', '', $storedDocxPath) . '.injected.docx';
            $disk->put($stored, (string) file_get_contents($work));
            @unlink($work);

            return $disk->exists($stored) ? $stored : null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Lowercased set of font families installed on the host (via fc-list). */
    public function installedFontFamilies(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (!function_exists('shell_exec') || stripos(PHP_OS, 'WIN') === 0) {
            return $cache = [];
        }
        try {
            $out = (string) @shell_exec('fc-list : family 2>/dev/null');
            $set = [];
            foreach (preg_split('/[\r\n]+/', $out) as $line) {
                foreach (explode(',', $line) as $fam) {
                    $fam = strtolower(trim($fam));
                    if ($fam !== '') {
                        $set[$fam] = true;
                    }
                }
            }

            return $cache = $set;
        } catch (\Throwable $e) {
            return $cache = [];
        }
    }

    /**
     * Is a font available on the host directly OR through a metric-compatible
     * substitute (same character widths → identical layout)? $installed is a
     * lowercased family set as returned by installedFontFamilies().
     */
    public function fontCoveredBy(string $font, array $installed): bool
    {
        $key = strtolower(trim($font));

        // A doc font is satisfied if ANY of these families is present.
        $aliases = [
            'arial' => ['arial', 'liberation sans', 'arimo'],
            'helvetica' => ['helvetica', 'liberation sans', 'arimo'],
            'times new roman' => ['times new roman', 'liberation serif', 'tinos'],
            'times' => ['times new roman', 'liberation serif', 'tinos'],
            'courier new' => ['courier new', 'liberation mono', 'cousine'],
            'courier' => ['courier new', 'liberation mono', 'cousine'],
            'calibri' => ['calibri', 'carlito'],
            'calibri light' => ['calibri light', 'calibri', 'carlito'],
            'cambria' => ['cambria', 'caladea'],
            'cambria math' => ['cambria math', 'caladea'],
        ];

        foreach ($aliases[$key] ?? [$key] as $candidate) {
            if (isset($installed[$candidate])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fonts a .docx needs that the host can't render (directly or via a
     * metric-compatible substitute) — the usual cause of shifted layout in the
     * converted PDF. Returns [] when detection isn't possible (no fc-list, not
     * a docx, no zip ext), so it never blocks or false-warns.
     */
    public function missingFonts(string $storedPath): array
    {
        $installed = $this->installedFontFamilies();
        if (empty($installed)) {
            return []; // can't introspect the host → don't guess
        }

        $missing = [];
        foreach ($this->fontsInDocx($storedPath) as $font) {
            if (!$this->fontCoveredBy($font, $installed)) {
                $missing[$font] = true;
            }
        }

        return array_keys($missing);
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
            $disk = Storage::disk();
            if (!$bin || !$disk->exists($storedPath)) {
                return null;
            }

            // Temp working dir INSIDE the app storage path (within open_basedir).
            $work = storage_path('app/tmp-convert/' . Str::random(14));
            if (!is_dir($work)) {
                @mkdir($work, 0775, true);
            }
            $inPath = $work . DIRECTORY_SEPARATOR . basename($storedPath);
            @file_put_contents($inPath, $disk->get($storedPath));

            $pdfLocal = $this->run($bin, $work, $inPath, 'pdf');
            if (!$pdfLocal) {
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

    /**
     * Convert a stored Office file to a single self-contained HTML string so
     * the admin can EDIT the text in the browser before converting to PDF.
     * Images LibreOffice exports beside the HTML are inlined as data: URIs.
     * Returns null when conversion isn't possible.
     */
    public function toEditableHtml(string $storedPath): ?string
    {
        try {
            $bin = $this->binary();
            $disk = Storage::disk();
            if (!$bin || !$disk->exists($storedPath)) {
                return null;
            }

            $work = storage_path('app/tmp-convert/' . Str::random(14));
            @mkdir($work, 0775, true);
            $inPath = $work . DIRECTORY_SEPARATOR . basename($storedPath);
            @file_put_contents($inPath, $disk->get($storedPath));

            $htmlLocal = $this->run($bin, $work, $inPath, 'html');
            if (!$htmlLocal) {
                $this->cleanup($work);
                return null;
            }

            $html = (string) file_get_contents($htmlLocal);

            // Inline exported images (LibreOffice writes them as sibling files).
            $html = preg_replace_callback('/src="([^"]+)"/i', function ($m) use ($work) {
                $src = html_entity_decode($m[1]);
                if (preg_match('#^(data:|https?://)#i', $src)) {
                    return $m[0];
                }
                $file = $work . DIRECTORY_SEPARATOR . basename($src);
                if (!@is_file($file)) {
                    return $m[0];
                }
                $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => 'image/jpeg',
                };

                return 'src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file)) . '"';
            }, $html);

            $this->cleanup($work);

            return $html;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Convert an (edited) HTML string to PDF, storing it at $targetStoredPath.
     * Returns the stored path, or null on failure.
     */
    public function htmlToPdf(string $html, string $targetStoredPath): ?string
    {
        try {
            $bin = $this->binary();
            if (!$bin) {
                return null;
            }

            $work = storage_path('app/tmp-convert/' . Str::random(14));
            @mkdir($work, 0775, true);
            $inPath = $work . DIRECTORY_SEPARATOR . 'document.html';
            @file_put_contents($inPath, $html);

            $pdfLocal = $this->run($bin, $work, $inPath, 'pdf:writer_web_pdf_Export');
            if (!$pdfLocal) {
                $this->cleanup($work);
                return null;
            }

            $disk = Storage::disk();
            $disk->put($targetStoredPath, (string) file_get_contents($pdfLocal));
            $this->cleanup($work);

            return $disk->exists($targetStoredPath) ? $targetStoredPath : null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Run soffice --headless --convert-to <format> and return the local output
     * path, or null if nothing usable was produced.
     *
     * A dedicated UserInstallation profile dir (inside storage, so within
     * open_basedir) lets LibreOffice run as the web user even when HOME
     * isn't writable — a common silent-failure cause on shared hosting.
     */
    private function run(string $bin, string $work, string $inPath, string $format): ?string
    {
        $profileDir = $work . DIRECTORY_SEPARATOR . 'profile';
        @mkdir($profileDir, 0775, true);
        $profileUri = 'file:///' . ltrim(str_replace('\\', '/', $profileDir), '/');
        $isWindows = stripos(PHP_OS, 'WIN') === 0;
        $envPrefix = $isWindows ? '' : 'HOME=' . escapeshellarg($work) . ' ';

        $binQuoted = Str::contains($bin, ' ') ? '"' . $bin . '"' : $bin;
        $cmd = $envPrefix . $binQuoted . ' --headless --norestore --nolockcheck '
            . '-env:UserInstallation=' . escapeshellarg($profileUri)
            . ' --convert-to ' . escapeshellarg($format) . ' --outdir '
            . escapeshellarg($work) . ' ' . escapeshellarg($inPath) . ' 2>&1';
        @shell_exec($cmd);

        // "pdf:writer_web_pdf_Export" → output extension "pdf".
        $ext = strtok($format, ':');
        $outPath = preg_replace('/\.[^.\\/\\\\]+$/', '.' . $ext, $inPath);

        return (@is_file($outPath) && @filesize($outPath) > 0) ? $outPath : null;
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
