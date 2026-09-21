@extends('layouts.legal')
@section('doc-title', 'Refund Policy')
@section('doc-body')
    <p>This Refund Policy explains how billing, cancellations and refunds work for paid subscriptions to the {{ config('legal.company') }} platform (the “Service”) provided by {{ config('legal.legal_entity') }}. It forms part of, and should be read with, our <a href="{{ route('legal.terms') }}">Terms of Service</a>.</p>

    <h2>1. Free trial</h2>
    <p>Where a free trial is offered, you are not charged during the trial. You can cancel any time before it ends and you will not be billed. If you do not subscribe before the trial ends, your workspace may be limited and later suspended.</p>

    <h2>2. Subscriptions &amp; billing</h2>
    <p>Paid plans are billed in advance on a recurring basis (monthly or annually, as selected) via our payment provider. Your subscription renews automatically until cancelled.</p>

    <h2>3. Cancellations</h2>
    <p>You can cancel your subscription at any time from your workspace’s billing settings. Cancellation stops future renewals; your plan remains active until the end of the current paid billing period, after which it will not renew. We do not provide pro-rata refunds for the unused part of a billing period unless required by law.</p>

    <h2>4. Refunds</h2>
    <p>Fees are generally non-refundable except where required by applicable law. We may, at our discretion, issue a refund in cases such as:</p>
    <ul>
        <li>A duplicate or clearly erroneous charge;</li>
        <li>A billing error on our part;</li>
        <li>A prolonged failure of the Service that we were unable to resolve.</li>
    </ul>
    <p>Approved refunds are returned to the original payment method. Consumer-protection rights that apply in your jurisdiction (including any statutory cooling-off period) are not affected by this policy.</p>

    <h2>5. Downgrades &amp; plan changes</h2>
    <p>You may change plans at any time. Upgrades take effect immediately; downgrades take effect at the start of your next billing cycle. Differences are handled through your payment provider and are not separately refunded.</p>

    <h2>6. How to request a refund</h2>
    <p>To request a refund or raise a billing question, contact us at <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a> with your workspace name and the charge in question. We aim to respond within a reasonable time.</p>

    <h2>7. Changes</h2>
    <p>We may update this Refund Policy from time to time. Material changes will be reflected here with an updated version date below.</p>
@endsection
