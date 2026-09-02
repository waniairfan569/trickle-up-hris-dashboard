<?php

/*
 | Legal document settings. `version` stamps each acceptance so you can require
 | re-acceptance after a material change (bump the date). The company details
 | fill the templated Terms / Privacy / DPA — replace with your real entity.
 */

return [
    'version' => env('LEGAL_VERSION', '2026-09-02'),

    'company' => env('LEGAL_COMPANY', 'Trickle Hub'),
    'legal_entity' => env('LEGAL_ENTITY', 'Trickle Hub Ltd'),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'privacy@tricklehub.com'),
    'jurisdiction' => env('LEGAL_JURISDICTION', 'England and Wales'),

    // Show the "template — review with legal counsel" banner until you've had
    // these reviewed and set this false.
    'is_template' => (bool) env('LEGAL_IS_TEMPLATE', true),
];
