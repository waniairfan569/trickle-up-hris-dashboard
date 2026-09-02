<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * A company user managing their own TOTP two-factor authentication from the
 * Settings › Security tab. Reuses the same TwoFactorService and the login
 * challenge (PageController diverts any 2FA-enabled user) that operators use —
 * this just gives non-operator accounts a place to enroll.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $tfa)
    {
    }

    private function backToSecurity(string $flashKey, string $message)
    {
        return redirect()->route('settings.index')->with('tab', 'security')->with($flashKey, $message);
    }

    /** Begin enrollment — generate an (unconfirmed) secret. */
    public function enable(Request $request)
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return $this->backToSecurity('error', 'Two-factor authentication is already enabled.');
        }

        $user->two_factor_secret = $this->tfa->generateSecret();
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return $this->backToSecurity('success', 'Scan the QR code with your authenticator app, then enter a code to finish.');
    }

    /** Confirm enrollment with a code from the authenticator app. */
    public function confirm(Request $request)
    {
        $user = $request->user();
        $request->validate(['code' => 'required|string']);

        abort_if(empty($user->two_factor_secret), 400, 'Start enabling 2FA first.');

        if (!$this->tfa->verify($user->two_factor_secret, $request->code)) {
            return redirect()->route('settings.index')->with('tab', 'security')
                ->withErrors(['code' => 'That code is not valid. Check your authenticator app and try again.']);
        }

        $user->two_factor_confirmed_at = now();
        $user->two_factor_recovery_codes = $this->tfa->recoveryCodes();
        $user->save();

        return $this->backToSecurity('success', 'Two-factor authentication is on. Save your recovery codes somewhere safe.');
    }

    /** Fresh recovery codes (invalidates the old set). */
    public function regenerateRecovery(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 400);

        $user->two_factor_recovery_codes = $this->tfa->recoveryCodes();
        $user->save();

        return $this->backToSecurity('success', 'New recovery codes generated — the old ones no longer work.');
    }

    /** Turn 2FA off — requires the account password. */
    public function disable(Request $request)
    {
        $user = $request->user();
        $request->validate(['password' => 'required|string']);

        if (!Hash::check($request->password, $user->password)) {
            return redirect()->route('settings.index')->with('tab', 'security')
                ->withErrors(['password' => 'Incorrect password.']);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return $this->backToSecurity('success', 'Two-factor authentication disabled.');
    }
}
