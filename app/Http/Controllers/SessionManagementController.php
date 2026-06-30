<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SessionManagementController extends Controller
{
    /** Admin: every active session across all users. */
    public function index(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $users = User::all(['id', 'first_name', 'last_name', 'email'])->keyBy('id');

        $sessions = DB::table('sessions')
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($s) use ($users, $request) {
                return [
                    'id' => $s->id,
                    'user' => $s->user_id ? $users->get($s->user_id) : null,
                    'user_id' => $s->user_id,
                    'ip' => $s->ip_address,
                    'device' => $this->deviceLabel($s->user_agent),
                    'last_active' => Carbon::createFromTimestamp($s->last_activity),
                    'is_current' => $s->id === $request->session()->getId(),
                ];
            });

        return view('admin.sessions.index', compact('sessions'));
    }

    /** Admin: revoke a single session (force that one device to log out). */
    public function revoke(Request $request, string $session)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        DB::table('sessions')->where('id', $session)->delete();

        return back()->with('success', 'Session revoked — that device will be signed out.');
    }

    /** Admin: log a user out everywhere (left company, lost device, suspicious activity). */
    public function revokeAllForUser(Request $request, User $user)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => null])->save();

        return back()->with('success', $user->full_name . ' has been signed out from all devices.');
    }

    /** Super admin emergency: sign everyone out (keeps the acting admin signed in). */
    public function forceLogoutEveryone(Request $request)
    {
        abort_unless($request->user() && $request->user()->hasRole('super_admin'), 403);
        $request->validate(['confirm' => 'required|in:CONFIRM']);

        $me = $request->user()->id;
        $current = $request->session()->getId();

        DB::table('sessions')->where('id', '!=', $current)->delete();
        User::where('id', '!=', $me)->update(['remember_token' => null]);

        return back()->with('success', 'All other users have been signed out from every device.');
    }

    // ---- User self-service -------------------------------------------------

    /** A user's own active sessions + the "log out other devices" control. */
    public function mySecurity(Request $request)
    {
        $user = $request->user();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'ip' => $s->ip_address,
                'device' => $this->deviceLabel($s->user_agent),
                'last_active' => Carbon::createFromTimestamp($s->last_activity),
                'is_current' => $s->id === $request->session()->getId(),
            ]);

        return view('sessions.security', compact('sessions'));
    }

    /** Log the current user out of every other device (password-confirmed). */
    public function logoutOtherDevices(Request $request)
    {
        $request->validate(['password' => 'required']);

        if (!Hash::check($request->password, $request->user()->password)) {
            return back()->withErrors(['password' => 'That password is incorrect.']);
        }

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'You have been signed out from all other devices.');
    }

    /** Best-effort "Browser on OS" label from a User-Agent string. */
    private function deviceLabel(?string $ua): string
    {
        if (!$ua) {
            return 'Unknown device';
        }

        $browser = 'Browser';
        foreach ([
            'Edg' => 'Edge', 'OPR' => 'Opera', 'Opera' => 'Opera',
            'Chrome' => 'Chrome', 'Firefox' => 'Firefox',
            'Safari' => 'Safari', 'MSIE' => 'Internet Explorer', 'Trident' => 'Internet Explorer',
        ] as $needle => $label) {
            if (stripos($ua, $needle) !== false) {
                $browser = $label;
                // Chrome UA also contains "Safari"; first match in this priority order wins.
                if (!in_array($needle, ['Safari'], true) || stripos($ua, 'Chrome') === false) {
                    break;
                }
            }
        }

        $os = 'Unknown OS';
        foreach ([
            'Windows NT 10' => 'Windows', 'Windows' => 'Windows',
            'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android',
            'Mac OS X' => 'macOS', 'Macintosh' => 'macOS', 'Linux' => 'Linux',
        ] as $needle => $label) {
            if (stripos($ua, $needle) !== false) {
                $os = $label;
                break;
            }
        }

        return $browser . ' on ' . $os;
    }
}
