@extends('layouts.legal')
@section('doc-title', 'Cookie Policy')
@section('doc-body')
    <p>This Cookie Policy explains how {{ config('legal.legal_entity') }} (“we”, “us”) uses cookies and similar technologies on the {{ config('legal.company') }} platform (the “Service”). It should be read alongside our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.</p>

    <h2>1. What cookies are</h2>
    <p>Cookies are small text files stored on your device when you visit a website. Similar technologies include local storage and session storage. They let a site remember your actions and preferences over time.</p>

    <h2>2. How we use cookies</h2>
    <p>We keep our use of cookies to what the Service needs to work. We use:</p>
    <ul>
        <li><strong>Strictly necessary cookies</strong> — required to sign you in and keep your session secure (for example the session cookie and the CSRF token). The Service cannot function without these.</li>
        <li><strong>Preference storage</strong> — remembers choices such as your theme (light/dark) and small UI conveniences, stored in your browser (local storage) on your device only.</li>
    </ul>
    <p>We do <strong>not</strong> use advertising or cross-site tracking cookies, and we do not sell your data.</p>

    <h2>3. Third-party cookies</h2>
    <p>Some features rely on trusted third parties that may set their own cookies when you use them — for example our payment provider (Stripe) during checkout, and our real-time and error-monitoring services. These are used to deliver and secure those features, not to profile you for advertising.</p>

    <h2>4. Managing cookies</h2>
    <p>Strictly necessary cookies cannot be switched off without breaking sign-in. You can control or delete other cookies and clear local storage through your browser settings. Blocking necessary cookies will prevent you from logging in.</p>

    <h2>5. Changes</h2>
    <p>We may update this Cookie Policy from time to time. Material changes will be reflected here with an updated version date below.</p>

    <p>Questions about cookies? Contact us at <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
@endsection
