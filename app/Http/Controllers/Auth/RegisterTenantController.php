<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Turnstile;
use App\Tenancy\TenantProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterTenantController extends Controller
{
    /** Public "Create your workspace" signup form. */
    public function show(Turnstile $turnstile)
    {
        return view('auth.register-tenant', [
            'turnstileSiteKey' => $turnstile->enabled() ? $turnstile->siteKey() : null,
        ]);
    }

    /** Provision a new agency (tenant + admin + defaults) and sign them in. */
    public function store(Request $request, TenantProvisioner $provisioner, Turnstile $turnstile)
    {
        // Bot / abuse check on the public form (no-op until Turnstile is configured).
        if (!$turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Please complete the “I’m human” check and try again.']);
        }

        $data = $request->validate([
            'company_name' => 'required|string|max:150',
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
            'terms' => 'accepted',
        ], [
            'email.unique' => 'That email is already registered. Try logging in instead.',
            'terms.accepted' => 'Please accept the Terms of Service and Privacy Policy to continue.',
        ]);

        [$tenant, $admin] = $provisioner->provision($data);

        // Record acceptance of the current Terms & Privacy version.
        $admin->forceFill([
            'terms_accepted_at' => now(),
            'terms_version' => config('legal.version'),
        ])->save();

        Auth::login($admin, true);
        $request->session()->regenerate();

        // Ask the owner to confirm their email. Never let a mail hiccup block
        // signup — they can resend from the notice page.
        try {
            $admin->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('verification.notice')
            ->with('success', "Welcome to {$tenant->name}! We've emailed a link to confirm your address — click it to unlock your workspace.");
    }
}
