@extends('layouts.legal')
@section('doc-title', 'Privacy Policy')
@section('doc-body')
    <p>This Privacy Policy explains how {{ config('legal.legal_entity') }} (“we”) handles personal data in connection with the {{ config('legal.company') }} platform. For personal data your organisation uploads about its own staff, your organisation is the data controller and we act as processor under the <a href="{{ route('legal.dpa') }}">Data Processing Agreement</a>.</p>

    <h2>1. Data we process</h2>
    <ul>
        <li><b>Account data</b> — names, work email, role, and authentication data for people who sign in.</li>
        <li><b>Customer Data</b> — the HR information your organisation stores (employees, attendance, documents, leave, etc.).</li>
        <li><b>Usage &amp; device data</b> — logs, IP address, and activity needed to secure and operate the Service.</li>
        <li><b>Billing data</b> — plan and payment metadata; card details are handled by our payment processor, not stored by us.</li>
    </ul>

    <h2>2. How we use it</h2>
    <p>To provide, secure, support and improve the Service; to process payments; to communicate about your account; and to comply with legal obligations. We do not sell personal data.</p>

    <h2>3. Legal bases</h2>
    <p>Where the UK/EU GDPR applies, we rely on performance of a contract, our legitimate interests in operating and securing the Service, consent (where required), and compliance with legal obligations.</p>

    <h2>4. Sharing</h2>
    <p>We share personal data with sub-processors that help us run the Service (e.g. hosting, email delivery, payment processing), each bound by appropriate contractual safeguards, and where required by law.</p>

    <h2>5. Retention</h2>
    <p>We retain personal data for as long as your workspace is active and as needed for the purposes above. On deletion of a workspace, Customer Data is deleted or anonymised within a reasonable period, subject to legal retention requirements.</p>

    <h2>6. Security</h2>
    <p>We use technical and organisational measures including tenant isolation, encryption in transit, access controls, optional two-factor authentication, audit logging and regular backups. No system is perfectly secure, and you are responsible for access you grant within your workspace.</p>

    <h2>7. International transfers</h2>
    <p>Where personal data is transferred across borders, we use appropriate safeguards such as standard contractual clauses.</p>

    <h2>8. Your rights</h2>
    <p>Subject to applicable law you may request access, correction, deletion, restriction, or portability of your personal data, and may object to certain processing. Workspace admins can export their workspace data in-app and request deletion. To exercise rights, contact <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>. For Customer Data, please contact your own organisation (the controller).</p>

    <h2>9. Contact</h2>
    <p>Questions about this policy: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
@endsection
