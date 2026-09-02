<?php

/*
 | SaaS subscription plans. Prices are per month. `seats` = max real employees
 | (0 = unlimited). `features` gates functionality ('*' = everything).
 | `stripe_price` is the Stripe Price ID used once Cashier is wired up.
 */

return [
    'currency' => 'USD',
    'currency_symbol' => '$',

    // Days a workspace stays reachable (upgrade wall only) after its trial ends,
    // before it is hard-suspended. 0 = suspend immediately on expiry.
    'trial_grace_days' => 14,

    // Days-left thresholds at which trialing workspaces get an expiry reminder.
    'trial_reminder_days' => [7, 3, 1],

    'plans' => [
        'trial' => [
            'name' => 'Trial',
            'price' => 0,
            'seats' => 25,
            'features' => ['*'],
            'stripe_price' => null,
            'selectable' => false,   // assigned automatically on signup
            'blurb' => 'Full access for 14 days.',
        ],
        'starter' => [
            'name' => 'Starter',
            'price' => 29,
            'seats' => 25,
            'features' => ['attendance', 'leave', 'documents', 'policies', 'announcements'],
            'stripe_price' => env('STRIPE_PRICE_STARTER'),
            'selectable' => true,
            'blurb' => 'The essentials for a small team.',
        ],
        'growth' => [
            'name' => 'Growth',
            'price' => 79,
            'seats' => 100,
            'features' => ['attendance', 'leave', 'documents', 'policies', 'announcements', 'esign', 'forms', 'reports', 'probation'],
            'stripe_price' => env('STRIPE_PRICE_GROWTH'),
            'selectable' => true,
            'popular' => true,
            'blurb' => 'For growing agencies that need e-sign & reports.',
        ],
        'scale' => [
            'name' => 'Scale',
            'price' => 199,
            'seats' => 0,
            'features' => ['*'],
            'stripe_price' => env('STRIPE_PRICE_SCALE'),
            'selectable' => true,
            'blurb' => 'Unlimited seats and every feature.',
        ],
    ],

    // Feature labels for the pricing table.
    'feature_labels' => [
        'attendance' => 'Attendance & clock-in',
        'leave' => 'Time off & policies',
        'documents' => 'Document library',
        'policies' => 'Company policies',
        'announcements' => 'Announcements',
        'esign' => 'E-signature documents',
        'forms' => 'Custom forms',
        'reports' => 'Attendance reports',
        'probation' => 'Probation tracking',
    ],
];
