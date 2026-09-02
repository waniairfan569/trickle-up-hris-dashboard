@extends('layouts.legal')
@section('doc-title', 'Terms of Service')
@section('doc-body')
    <p>These Terms of Service (“Terms”) govern your access to and use of the {{ config('legal.company') }} platform (the “Service”) provided by {{ config('legal.legal_entity') }} (“we”, “us”). By creating a workspace or using the Service, you agree to these Terms.</p>

    <h2>1. Accounts &amp; workspaces</h2>
    <p>You must provide accurate information and are responsible for activity under your workspace, including your team members’ use. You are responsible for keeping credentials secure and for configuring appropriate access controls for your users.</p>

    <h2>2. Acceptable use</h2>
    <p>You agree not to misuse the Service. You will not:</p>
    <ul>
        <li>Break the law or infringe others’ rights when using the Service;</li>
        <li>Attempt to gain unauthorised access to the Service, other workspaces, or its systems;</li>
        <li>Upload malware, or interfere with or disrupt the integrity or performance of the Service;</li>
        <li>Resell or provide the Service to third parties except as permitted in writing.</li>
    </ul>

    <h2>3. Your data</h2>
    <p>You retain ownership of the data you and your team put into the Service (“Customer Data”). You grant us a limited licence to host and process Customer Data solely to provide and support the Service. Our handling of personal data is described in the <a href="{{ route('legal.privacy') }}">Privacy Policy</a> and, where we act as your processor, the <a href="{{ route('legal.dpa') }}">Data Processing Agreement</a>.</p>

    <h2>4. Subscriptions &amp; billing</h2>
    <p>Paid plans are billed in advance on a recurring basis. Fees are non-refundable except where required by law. We may change pricing with reasonable notice; changes apply to your next billing cycle. Failure to pay may result in suspension.</p>

    <h2>5. Trials</h2>
    <p>Free trials are provided as-is and may be limited or ended at any time. If you do not subscribe before a trial ends, your workspace may be limited and later suspended.</p>

    <h2>6. Availability</h2>
    <p>We work to keep the Service available but do not guarantee uninterrupted access. We may perform maintenance and may modify or discontinue features.</p>

    <h2>7. Termination</h2>
    <p>You may stop using the Service and delete your workspace at any time. We may suspend or terminate access for breach of these Terms. On termination, you may export your data for a reasonable period, after which it may be deleted.</p>

    <h2>8. Disclaimers &amp; liability</h2>
    <p>The Service is provided “as is” without warranties of any kind to the extent permitted by law. To the maximum extent permitted by law, our aggregate liability arising out of the Service is limited to the amounts you paid us in the twelve months before the claim.</p>

    <h2>9. Governing law</h2>
    <p>These Terms are governed by the laws of {{ config('legal.jurisdiction') }}, and the courts of {{ config('legal.jurisdiction') }} have exclusive jurisdiction, without prejudice to mandatory consumer protections in your place of residence.</p>

    <h2>10. Changes</h2>
    <p>We may update these Terms; material changes will be notified and may require re-acceptance. Continued use after changes take effect constitutes acceptance.</p>
@endsection
