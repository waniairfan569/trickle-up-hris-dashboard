<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

/**
 * Email confirmation for public self-signup owners. Existing / invited /
 * admin-created accounts are auto-verified (see User), so only a fresh
 * self-signup ever lands here.
 */
class VerifyEmailController extends Controller
{
    /** The "please confirm your email" holding page. */
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->intended('/dashboard')
            : view('auth.verify-email');
    }

    /** Signed link from the email — confirm and let them in. */
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->intended('/dashboard')
            ->with('success', 'Your email is confirmed — your workspace is ready.');
    }

    /** Resend the link. */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'A fresh confirmation link is on its way.');
    }
}
