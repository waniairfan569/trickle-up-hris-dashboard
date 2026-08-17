@php
    // Embed the yellow "u" logo mark as base64 so DomPDF always resolves it.
    $lhLogoPath = public_path('images/logo.png');
    $lhLogo = is_file($lhLogoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($lhLogoPath)) : null;
@endphp
<style>
    /* Fixed letterhead header + footer — DomPDF repeats these on every page. */
    .lh-header { position: fixed; top: 0; left: 0; right: 0; height: 74pt; background: #FFFDF5; border-bottom: 2.5pt solid #fcd82f; }
    .lh-header td { vertical-align: middle; }
    .lh-brand { text-align: right; padding: 0 26pt; }
    .lh-word { font-family: 'DejaVu Sans'; font-weight: bold; font-size: 22pt; color: #1a1a24; letter-spacing: 1pt; }
    .lh-u { height: 21pt; vertical-align: -4pt; }
    .lh-sub { font-size: 7pt; letter-spacing: 4pt; color: #1a1a24; margin-top: 2pt; }

    .lh-footer { position: fixed; bottom: 0; left: 0; right: 0; height: 54pt; background: #1F5FD6; color: #ffffff; }
    .lh-footer td { vertical-align: middle; }
    .lf-addr { font-size: 8.5pt; line-height: 1.35; color: #ffffff; }
    .lf-line { font-size: 9pt; color: #ffffff; padding: 1.5pt 0; }
    .lf-line .u { text-decoration: underline; }
    .lf-ico { vertical-align: -2pt; }
</style>

<div class="lh-header">
    <table style="width:100%; height:74pt;">
        <tr>
            <td class="lh-brand">
                <span class="lh-word">TRICKLE</span>@if($lhLogo)<img class="lh-u" src="{{ $lhLogo }}">@else<span class="lh-word" style="color:#fcd82f;">u</span>@endif<span class="lh-word">P</span>
                <div class="lh-sub">PRIVATE LIMITED</div>
            </td>
        </tr>
    </table>
</div>

<div class="lh-footer">
    <table style="width:100%; height:54pt;">
        <tr>
            <td style="padding-left:26pt;">
                <table><tr>
                    <td style="width:26pt; vertical-align:middle;">
                        <svg width="20" height="20" viewBox="0 0 24 24"><path d="M3 21V6.5L11 3v18H3z M11 21V10l8 3.5V21h-8z" fill="#fcd82f"/><rect x="5" y="8" width="1.6" height="1.6" fill="#1F5FD6"/><rect x="8" y="8" width="1.6" height="1.6" fill="#1F5FD6"/><rect x="5" y="12" width="1.6" height="1.6" fill="#1F5FD6"/><rect x="8" y="12" width="1.6" height="1.6" fill="#1F5FD6"/></svg>
                    </td>
                    <td class="lf-addr">Plot 50, Business Bay, Phase 7 Sector F Bahria Town,<br>Rawalpindi, 44000 Pakistan</td>
                </tr></table>
            </td>
            <td style="text-align:right; padding-right:26pt;">
                <div class="lf-line"><svg class="lf-ico" width="15" height="15" viewBox="0 0 24 24"><path d="M2 5h20v14H2V5zm2.2 2L12 12l7.8-5H4.2zM4 8.4V17h16V8.4l-8 5-8-5z" fill="#fcd82f"/></svg> hello@trickleup.co.uk</div>
                <div class="lf-line"><svg class="lf-ico" width="15" height="15" viewBox="0 0 24 24"><path d="M2 21l20-9L2 3v6.5l13 2.5-13 2.5V21z" fill="#fcd82f"/></svg> <span class="u">www.trickleup.co.uk</span></div>
            </td>
        </tr>
    </table>
</div>
