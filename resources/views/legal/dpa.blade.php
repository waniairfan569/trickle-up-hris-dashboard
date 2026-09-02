@extends('layouts.legal')
@section('doc-title', 'Data Processing Agreement')
@section('doc-body')
    <p>This Data Processing Agreement (“DPA”) forms part of the <a href="{{ route('legal.terms') }}">Terms of Service</a> between your organisation (“Controller”) and {{ config('legal.legal_entity') }} (“Processor”) and applies where we process personal data on your behalf under UK/EU data protection law.</p>

    <h2>1. Roles &amp; scope</h2>
    <p>You are the Controller of the Customer Data you upload about your staff; we are the Processor. We process Customer Data only to provide the Service and on your documented instructions (including as configured through the product).</p>

    <h2>2. Processor obligations</h2>
    <ul>
        <li>Process personal data only on your instructions and for the Service;</li>
        <li>Ensure personnel with access are bound by confidentiality;</li>
        <li>Implement appropriate technical and organisational security measures;</li>
        <li>Assist you, taking into account the nature of processing, with data subject requests and with your security, breach-notification and impact-assessment obligations;</li>
        <li>Notify you without undue delay after becoming aware of a personal data breach.</li>
    </ul>

    <h2>3. Sub-processors</h2>
    <p>You authorise us to engage sub-processors (e.g. hosting, email, payments) to support the Service, subject to written terms imposing equivalent data-protection obligations. We remain responsible for their performance and will inform you of intended changes so you may object on reasonable grounds.</p>

    <h2>4. Data subject rights</h2>
    <p>Taking into account the nature of the processing, we will assist you by appropriate technical and organisational measures — including in-app export and deletion tools — to respond to requests to exercise data subject rights.</p>

    <h2>5. International transfers</h2>
    <p>Where we transfer personal data internationally, we will do so subject to appropriate safeguards such as standard contractual clauses.</p>

    <h2>6. Return &amp; deletion</h2>
    <p>On termination or at your request, we will delete or return Customer Data (at your choice) and delete existing copies except where storage is required by law. Workspace deletion permanently removes Customer Data after a short backup-retention window.</p>

    <h2>7. Audits</h2>
    <p>We will make available information reasonably necessary to demonstrate compliance with this DPA and allow for and contribute to audits, subject to reasonable confidentiality and security conditions.</p>

    <p class="mt-4 text-xs text-slate-400">For a signed copy or enterprise DPA terms, contact <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
@endsection
