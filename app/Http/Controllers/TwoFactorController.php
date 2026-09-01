<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** An operator managing their own TOTP two-factor authentication. */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $tfa) {}

    public function show(Request $request)
    {
        $user = $request->user();

        $state = $user->hasTwoFactorEnabled() ? 'enabled'
            : (! empty($user->two_factor_secret) ? 'pending' : 'disabled');

        $qrUri = null;
        if ($state === 'pending') {
            $qrUri = $this->tfa->otpauthUri($user->two_factor_secret, $user->email);
        }

        return view('operator.security', [
            'user'          => $user,
            'state'         => $state,
            'qrUri'         => $qrUri,
            'secret'        => $state === 'pending' ? $user->two_factor_secret : null,
            'recoveryCodes' => $state === 'enabled' ? ($user->two_factor_recovery_codes ?? []) : [],
        ]);
    }

    /** Begin enrollment — generate an (unconfirmed) secret. */
    public function enable(Request $request)
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->with('error', 'Two-factor authentication is already enabled.');
        }

        $user->two_factor_secret = $this->tfa->generateSecret();
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return redirect()->route('operator.security')->with('success', 'Scan the QR code, then enter a code to finish enabling 2FA.');
    }

    /** Confirm enrollment with a code from the authenticator app. */
    public function confirm(Request $request)
    {
        $user = $request->user();
        $request->validate(['code' => 'required|string']);

        abort_if(empty($user->two_factor_secret), 400, 'Start enabling 2FA first.');

        if (! $this->tfa->verify($user->two_factor_secret, $request->code)) {
            return back()->withErrors(['code' => 'That code is not valid. Check your authenticator app and try again.']);
        }

        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = $this->tfa->recoveryCodes();
        $user->save();

        return redirect()->route('operator.security')->with('success', 'Two-factor authentication is on. Save your recovery codes somewhere safe.');
    }

    public function regenerateRecovery(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 400);

        $user->two_factor_recovery_codes = $this->tfa->recoveryCodes();
        $user->save();

        return back()->with('success', 'New recovery codes generated — the old ones no longer work.');
    }

    /** Turn 2FA off — requires the account password. */
    public function disable(Request $request)
    {
        $user = $request->user();
        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return redirect()->route('operator.security')->with('success', 'Two-factor authentication disabled.');
    }
}
