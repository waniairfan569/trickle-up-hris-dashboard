<?php

namespace App\Http\Controllers;

use App\Models\CompanyPolicy;
use App\Models\PolicyAcknowledgment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PolicyAcknowledgmentController extends Controller
{
    public function myPolicies(Request $request)
    {
        $user = $request->user();
        $acks = $user->policyAcknowledgments()
            ->with('policy')
            ->get()
            ->filter(fn ($a) => $a->policy && $a->policy->status === 'active')
            ->sortByDesc('id')
            ->values();

        return view('employee.policies.index', compact('acks'));
    }

    public function view(Request $request, CompanyPolicy $companyPolicy)
    {
        $user = $request->user();
        abort_if($companyPolicy->status !== 'active' && !$user->isAdmin(), 404);
        $ack = $this->authorizeAndGetAck($companyPolicy, $user);

        // Mark as viewed (first time).
        if ($ack && $ack->status === 'pending') {
            $ack->update(['status' => 'viewed', 'viewed_at' => now()]);
        }

        return view('employee.policies.view', ['policy' => $companyPolicy, 'ack' => $ack?->fresh()]);
    }

    public function acknowledge(Request $request, CompanyPolicy $companyPolicy)
    {
        $user = $request->user();
        $ack = $this->authorizeAndGetAck($companyPolicy, $user);
        abort_unless($ack, 403);

        $rules = ['confirmed' => 'accepted'];
        if ($companyPolicy->requires_signature) {
            $rules['signature_type'] = ['required', Rule::in(['typed', 'drawn'])];
        }
        $request->validate($rules, ['confirmed.accepted' => 'You must confirm you have read the policy.']);

        $signatureName = null;
        $signatureData = null;
        if ($companyPolicy->requires_signature) {
            if ($request->signature_type === 'typed') {
                $request->validate(['signature_name' => 'required|string|max:120']);
                $signatureName = $request->signature_name;
            } else {
                $request->validate(['signature_data' => 'required|string']);
                $signatureData = $request->signature_data;
            }
        }

        $ack->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'viewed_at' => $ack->viewed_at ?? now(),
            'signature_type' => $companyPolicy->requires_signature ? $request->signature_type : null,
            'signature_name' => $signatureName,
            'signature_data' => $signatureData,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return redirect()->route('my-policies.index')->with('success', 'Policy acknowledged successfully.');
    }

    private function authorizeAndGetAck(CompanyPolicy $policy, $user): ?PolicyAcknowledgment
    {
        $ack = $policy->acknowledgments()->where('user_id', $user->id)->first();

        if (!$ack) {
            $assigned = $policy->getAssignedUsers()->pluck('id')->contains($user->id);
            abort_unless($assigned || $user->isAdmin(), 403, 'This policy is not assigned to you.');
            if ($assigned) {
                $ack = $policy->acknowledgments()->create(['user_id' => $user->id, 'status' => 'pending']);
            }
        }

        return $ack;
    }
}
