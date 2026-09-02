<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Simple page + auth actions that were previously inline route closures.
 * Moving them into a controller lets `php artisan route:cache` succeed
 * (closures can't be serialized), so production deploys don't abort.
 */
class PageController extends Controller
{
    public function welcome()
    {
        return view('welcome');
    }

    /** Public pricing page, driven by the live plans. */
    public function pricing()
    {
        return view('pricing', [
            'plans' => \App\Models\Plan::public()->ordered()->get(),
            'featureLabels' => \App\Models\PlanFeature::labels(),
            'symbol' => config('plans.currency_symbol', '$'),
            'currency' => config('plans.currency', 'USD'),
        ]);
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Brute-force protection: lock out after repeated failures per email+IP.
        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} second" . ($seconds === 1 ? '' : 's') . '.',
            ]);
        }

        // "Remember me" is opt-in (unchecked → session ends per the session lifetime).
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Block accounts that aren't allowed to sign in yet / anymore.
            if (in_array(Auth::user()->account_status, ['deactivated', 'suspended', 'invited'], true)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'email' => 'This account is not active. Please contact your administrator.',
                ]);
            }
            $user = Auth::user();

            // Two-factor: password was right, but don't complete login yet —
            // log back out, remember who they are, and challenge for a code.
            if ($user->hasTwoFactorEnabled()) {
                Auth::logout();
                $request->session()->put('2fa.user_id', $user->id);
                $request->session()->put('2fa.remember', $request->boolean('remember'));
                RateLimiter::clear($throttleKey);

                return redirect()->route('two-factor.challenge');
            }

            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            // Platform operators land in their own console, not the company app.
            if ($user->isOperator()) {
                return redirect()->route('operator.index');
            }

            return redirect()->intended('/dashboard');
        }

        // Failed attempt — count it toward the lockout (decays after 60s).
        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function mySchedule(Request $request)
    {
        $user = $request->user();
        $activeAssignment = $user->shiftAssignments()
            ->with('shift')
            ->where('assignment_type', 'recurring')
            ->whereNull('recurring_end_date')
            ->first();

        return view('shifts.my-schedule', compact('activeAssignment'));
    }
}
