@php
    // The letterhead to render: passed in, else the workspace default.
    $lh = ($letterhead ?? null) ?: \App\Models\LetterheadTemplate::default();
@endphp

@if($lh)
    @php
        $headerImg = $lh->headerImageData();
        $footerImg = $lh->footerImageData();
        $watermark = $lh->watermarkImageData();
        $lhLogo    = $lh->logoData();
    @endphp

    @if($watermark)
        {{-- Faint page watermark behind the body (repeats on every page). z-index keeps it under the text. --}}
        <div style="position: fixed; top: 27%; left: 0; right: 0; text-align: center; z-index: -1;">
            <img src="{{ $watermark }}" style="width: 300pt; opacity: {{ $lh->watermark_opacity ?: 0.06 }};">
        </div>
    @endif

    <style>
        .lh-header { position: fixed; top: 0; left: 0; right: 0; height: 74pt; background: {{ $headerImg ? '#ffffff' : ($lh->header_bg ?: '#FFFDF5') }};@if(! $headerImg) border-bottom: 2.5pt solid {{ $lh->header_accent ?: '#fcd82f' }};@endif }
        .lh-header td { vertical-align: middle; }
        .lh-word { font-family: 'DejaVu Sans'; font-weight: bold; font-size: 20pt; color: #1a1a24; letter-spacing: 1pt; }
        .lh-sub { font-size: 7pt; letter-spacing: 3pt; color: #1a1a24; margin-top: 2pt; }
        .lh-logo { height: 42pt; }
        .lh-brandimg { height: 34pt; }

        .lh-footer { position: fixed; bottom: 0; left: 0; right: 0; height: {{ $footerImg ? '50pt' : '54pt' }};@if(! $footerImg) background: {{ $lh->footer_bg ?: '#1F5FD6' }}; color: {{ $lh->footer_text ?: '#ffffff' }};@endif }
        .lh-footer td { vertical-align: middle;@if(! $footerImg) color: {{ $lh->footer_text ?: '#ffffff' }};@endif }
        .lf-cell { font-size: 8pt; height: 54pt; vertical-align: middle; }
        .lh-footimg { display: block; width: 100%; height: 50pt; }
    </style>

    <div class="lh-header">
        @if($headerImg)
            <table style="width:100%; height:74pt;"><tr><td style="padding-left:26pt;"><img class="lh-brandimg" src="{{ $headerImg }}"></td></tr></table>
        @else
            <table style="width:100%; height:74pt;"><tr>
                <td style="width:90pt; text-align:left; padding-left:26pt;">@if($lhLogo)<img class="lh-logo" src="{{ $lhLogo }}">@endif</td>
                <td style="text-align:right; padding-right:26pt;">
                    <span class="lh-word">{{ $lh->company_name }}</span>
                    @if($lh->company_suffix)<div class="lh-sub">{{ $lh->company_suffix }}</div>@endif
                </td>
            </tr></table>
        @endif
    </div>

    @if($footerImg)
        <div class="lh-footer"><img class="lh-footimg" src="{{ $footerImg }}"></div>
    @else
        @php
            $lfColor = $lh->footer_text ?: '#ffffff';
            // PNG icons (base64) tinted to the footer text colour — reliable in DomPDF.
            $icMail = $lh->iconDataUri('mail', $lfColor);
            $icBldg = $lh->iconDataUri('building', $lfColor);
            $icWeb  = $lh->iconDataUri('globe', $lfColor);
            $lfIcon = fn ($src) => $src ? '<img src="' . $src . '" style="height:10pt; width:10pt; vertical-align:-1.5pt; margin-right:4pt;">' : '';
            $lfAddr = $lh->address ? \Illuminate\Support\Str::of($lh->address)->replace("\n", ', ') : null;
        @endphp
        <div class="lh-footer">
            <table style="width:100%; height:54pt;"><tr>
                @if($lh->email)<td style="width:30%; padding-left:26pt; text-align:left;" class="lf-cell">{!! $lfIcon($icMail) !!}{{ $lh->email }}</td>@endif
                @if($lfAddr)<td style="text-align:center;" class="lf-cell">{!! $lfIcon($icBldg) !!}{{ $lfAddr }}</td>@endif
                <td style="width:30%; padding-right:26pt; text-align:right;" class="lf-cell">
                    @if($lh->website){!! $lfIcon($icWeb) !!}{{ $lh->website }}@endif
                    @if($lh->phone)@if($lh->website) &nbsp;·&nbsp; @endif{{ $lh->phone }}@endif
                </td>
            </tr></table>
        </div>
    @endif
@else
    {{-- Fallback: the built-in Trickle Up letterhead (used until a workspace sets one up). --}}
    @php
        $lhLogoPath = public_path('images/logo.png');
        $lhLogo = is_file($lhLogoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($lhLogoPath)) : null;
    @endphp
    <style>
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
@endif
