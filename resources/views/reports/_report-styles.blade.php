<style>
    @page { margin: 98pt 0 76pt 0; }   /* top clears the letterhead header, bottom the footer bar (with a gap) */
    thead { display: table-header-group; }   /* repeat table headers on every page */
    table.data tr { page-break-inside: avoid; }
    * { margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 9pt; }
    .wrap { padding: 0 26pt 26pt; }

    /* Header */
    .doc-header { width: 100%; background: #1a1a24; color: #ffffff; }
    .doc-header td { padding: 16pt 26pt; vertical-align: middle; }
    .brand { font-size: 16pt; font-weight: bold; }
    .brand-sub { font-size: 9pt; color: #AEB9D6; margin-top: 2pt; }
    .brand-period { font-size: 11pt; color: #ffffff; margin-top: 3pt; font-weight: bold; }
    .conf { text-align: right; }
    .conf span { border: 1pt solid #6B7BB0; color: #C7D0EA; font-size: 7pt; font-weight: bold; letter-spacing: 1pt; padding: 3pt 7pt; }

    /* Employee info bar */
    .info { width: 100%; background: #EEF2FB; }
    .info td { padding: 6pt 26pt; font-size: 8.5pt; color: #334155; width: 50%; border-bottom: 1pt solid #dbe3f4; }
    .info b { color: #1a1a24; }

    /* Summary cards */
    .cards { width: 100%; margin-top: 16pt; border-collapse: separate; border-spacing: 6pt 0; }
    .cards td { width: 25%; padding: 11pt 8pt; text-align: center; vertical-align: top; }
    .card-num { font-size: 17pt; font-weight: bold; }
    .card-lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .5pt; margin-top: 3pt; }
    .c-green { background: #ECFDF5; } .c-green .card-num, .c-green .card-lbl { color: #047857; }
    .c-red   { background: #FEF2F2; } .c-red .card-num,   .c-red .card-lbl   { color: #B91C1C; }
    .c-amber { background: #FFFBEB; } .c-amber .card-num, .c-amber .card-lbl { color: #B45309; }
    .c-blue  { background: #EFF6FF; } .c-blue .card-num,  .c-blue .card-lbl  { color: #1D4ED8; }

    /* Attendance rate bar */
    .rate { margin-top: 16pt; }
    .rate-top { font-size: 8.5pt; margin-bottom: 4pt; }
    .rate-track { width: 100%; height: 12pt; background: #E5E7EB; }
    .rate-fill { height: 12pt; }

    /* Sections + tables */
    .sec { margin-top: 16pt; }
    .sec-title { font-size: 10pt; font-weight: bold; color: #1a1a24; border-bottom: 2pt solid #1a1a24; padding-bottom: 4pt; margin-bottom: 8pt; }
    table.data { width: 100%; border-collapse: collapse; font-size: 8pt; }
    table.data th { background: #1a1a24; color: #fff; text-align: left; padding: 5pt 6pt; font-size: 7.5pt; }
    table.data td { padding: 4.5pt 6pt; border-bottom: 1pt solid #E5E7EB; }
    table.data tr:nth-child(even) td { background: #F8FAFC; }
    .num { text-align: center; }
    .tot td { font-weight: bold; background: #EEF2FB; border-top: 1.5pt solid #1a1a24; }
    .st { font-weight: bold; }
    .st-present { color: #059669; }
    .st-late { color: #B45309; }
    .st-absent { color: #DC2626; }
    .st-on_leave { color: #2563EB; }
    .st-overtime { color: #7C3AED; }
    .st-early_departure { color: #B45309; }
    .st-missing_clock_out { color: #EA580C; }
    .st-half_day { color: #0891B2; }
    .st-weekend { color: #94A3B8; }

    /* Leave requests */
    .lv-type { font-size: 8.5pt; font-weight: bold; color: #1a1a24; margin-top: 8pt; }
    .lv-item { font-size: 8pt; color: #334155; margin: 2pt 0 0 12pt; }
    .lv-reason { color: #64748B; }

    /* Performance score */
    .score { width: 100%; margin-top: 4pt; }
    .score td { vertical-align: middle; }
    .score-badge { width: 62pt; height: 62pt; color: #fff; text-align: center; }
    .score-val { font-size: 20pt; font-weight: bold; padding-top: 12pt; }
    .score-max { font-size: 7pt; }
    .score-grade { font-size: 12pt; font-weight: bold; }
    .score-bar-track { width: 180pt; height: 10pt; background: #E5E7EB; margin-top: 5pt; }
    .score-bar-fill { height: 10pt; }

    .footer { margin-top: 18pt; border-top: 1pt solid #E5E7EB; padding-top: 8pt; font-size: 7.5pt; color: #94A3B8; }
    .muted { color: #94A3B8; }
    .page-break { page-break-after: always; }
</style>
