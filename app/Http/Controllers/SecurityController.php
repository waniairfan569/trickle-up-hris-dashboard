<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Security hub — one landing page with a tile per security tool (my own
 * sessions, roles & permissions, everyone's sessions, audit log), so the
 * sidebar carries a single "Security" link instead of a dropdown. Admin-only,
 * same as the pages it links to.
 */
class SecurityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tenantUserIds = User::pluck('id'); // User is tenant-scoped

        $activeSince = now()->subMinutes(15)->timestamp;
        $mySessions = DB::table('sessions')->where('user_id', $user->id)->count();
        $allSessions = DB::table('sessions')->whereIn('user_id', $tenantUserIds->all());
        $allCount = $allSessions->count();
        $activeNow = (clone $allSessions)->where('last_activity', '>=', $activeSince)->count();

        $tiles = [
            [
                'route' => 'account.security',
                'icon' => 'lock',
                'tone' => 'brand',
                'title' => 'Security',
                'text' => 'Your own signed-in devices — review them and sign out everywhere else.',
                'stat' => $mySessions,
                'stat_label' => 'my devices',
                'meta' => $user->hasTwoFactorEnabled() ? '2FA on' : '2FA off',
            ],
            [
                'route' => 'roles.index',
                'icon' => 'shield',
                'tone' => 'violet',
                'title' => 'Roles & Permissions',
                'text' => 'What each role can see and do across the workspace.',
                'stat' => Role::count(),
                'stat_label' => 'roles',
                'meta' => null,
            ],
            [
                'route' => 'admin.sessions.index',
                'icon' => 'monitor-smartphone',
                'tone' => 'sky',
                'title' => 'Active Sessions',
                'text' => "Everyone who's signed in — revoke a device or force a sign-out.",
                'stat' => $activeNow,
                'stat_label' => 'active in last 15 min',
                'meta' => $allCount . ' sessions total',
            ],
            [
                'route' => 'admin.audit-logs',
                'icon' => 'history',
                'tone' => 'amber',
                'title' => 'System Audit Logs',
                'text' => 'Who changed what, and when — searchable history of admin actions.',
                'stat' => ActivityLog::whereDate('created_at', today())->count(),
                'stat_label' => 'events today',
                'meta' => ActivityLog::where('created_at', '>=', Carbon::now()->subDays(7))->count() . ' this week',
            ],
        ];

        return view('security.index', compact('tiles'));
    }
}
