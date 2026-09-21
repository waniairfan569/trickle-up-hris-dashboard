<?php

namespace App\Http\Controllers;

use App\Tenancy\TenantManager;
use Illuminate\Http\Request;

/**
 * The one-time "tell us about your company" questionnaire a new workspace owner
 * answers right after signing up. Answers are stored on the tenant; either path
 * (answer or skip) stamps onboarding_survey_at so the popup never shows again.
 */
class OnboardingSurveyController extends Controller
{
    /** Allowed answers — kept in sync with the modal's option lists. */
    private const SIZES = ['1-10', '11-50', '51-200', '201-500', '500+'];
    private const HEARD = ['Search engine', 'Social media', 'Friend or colleague', 'Advertisement', 'Event', 'Other'];

    public function store(Request $request, TenantManager $tenants)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        $data = $request->validate([
            'company_size' => ['required', 'in:' . implode(',', self::SIZES)],
            'industry'     => ['nullable', 'string', 'max:80'],
            'country'      => ['nullable', 'string', 'max:80'],
            'heard_from'   => ['nullable', 'in:' . implode(',', self::HEARD)],
        ]);

        $tenant->forceFill([
            'company_size'         => $data['company_size'],
            'industry'             => $data['industry'] ?: null,
            'country'              => $data['country'] ?: null,
            'heard_from'           => $data['heard_from'] ?? null,
            'onboarding_survey_at' => now(),
        ])->save();

        return back()->with('success', 'Thanks — your workspace is all set. You can update these anytime in Organization Profile.');
    }

    /** "Skip for now" — mark it done so we don't ask again, without capturing answers. */
    public function skip(TenantManager $tenants)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        if ($tenant->needsOnboardingSurvey()) {
            $tenant->forceFill(['onboarding_survey_at' => now()])->save();
        }

        return back();
    }
}
