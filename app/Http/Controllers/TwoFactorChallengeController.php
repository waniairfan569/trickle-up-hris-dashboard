<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The second step of login for a 2FA-enabled account. The user has passed the
 * password check but is NOT yet authenticated — their id sits in the session.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactorService $tfa) {}

    public function show(Request $request)
    {
        if (! $request->session()->has('2fa.user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $userId = $request->session()->get('2fa.user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $key = '2fa:' . $userId;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['code' => "Too many attempts. Try again in {$seconds}s."]);
        }

        $user = User::find($userId);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['2fa.user_id', '2fa.remember']);

            return redirect()->route('login');
        }

        $code = trim($request->code);
        $ok = false;
        $usedRecovery = false;

        // A recovery code (contains a dash) is one-time; otherwise it's a TOTP code.
        if (str_contains($code, '-')) {
            $codes = $user->two_factor_recovery_codes ?? [];
            if (in_array(strtoupper($code), array_map('strtoupper', $codes), true)) {
                $ok = true;
                $usedRecovery = true;
            }
        } else {
            $ok = $this->tfa->verify($user->two_factor_secret, $code);
        }

        if (! $ok) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => 'That code is not valid.']);
        }

        RateLimiter::clear($key);

        // Consume the used recovery code.
        if ($usedRecovery) {
            $user->two_factor_recovery_codes = array_values(array_filter(
                $user->two_factor_recovery_codes ?? [],
                fn ($c) => strtoupper($c) !== strtoupper($code)
            ));
            $user->save();
        }

        $remember = (bool) $request->session()->get('2fa.remember');
        $request->session()->forget(['2fa.user_id', '2fa.remember']);

        Auth::loginUsingId($user->id, $remember);
        $request->session()->regenerate();

        return $user->isOperator()
            ? redirect()->route('operator.index')
            : redirect()->intended('/dashboard');
    }
}
