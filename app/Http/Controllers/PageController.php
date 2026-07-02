<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // Always "remember" so the session persists for the full lifetime.
        if (Auth::attempt($credentials, true)) {
            // Block archived (deactivated) / suspended accounts from signing in.
            if (in_array(Auth::user()->account_status, ['deactivated', 'suspended'], true)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'email' => 'This account has been deactivated. Please contact your administrator.',
                ]);
            }
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

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
