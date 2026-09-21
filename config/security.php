<?php

/*
 | Security header settings. The Content-Security-Policy is emitted in
 | report-only mode by default (it monitors violations without blocking), so it
 | is safe to ship. Once you've watched for violations and are confident, set
 | CSP_ENFORCE=true to switch it to a blocking policy.
 */

return [
    // true  → send an enforcing `Content-Security-Policy` header.
    // false → send `Content-Security-Policy-Report-Only` (monitor only).
    'csp_enforce' => (bool) env('CSP_ENFORCE', false),

    // Optional endpoint browsers POST CSP violation reports to (report-uri).
    'csp_report_uri' => env('CSP_REPORT_URI'),
];
