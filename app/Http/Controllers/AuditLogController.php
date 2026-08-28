<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** Admin: browse the system audit trail (who did what, when, from where). */
    public function index(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $query = ActivityLog::with('user')->latest('created_at');

        if ($request->filled('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }
        if ($request->filled('entity_type') && $request->entity_type !== 'all') {
            $query->where('entity_type', $request->entity_type);
        }
        if ($request->filled('user_id') && $request->user_id !== 'all') {
            $query->where('user_id', $request->user_id);
        }
        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(fn ($q) => $q->where('description', 'like', "%{$search}%")
                ->orWhere('ip_address', 'like', "%{$search}%"));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(25)->withQueryString();

        $actions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $entities = ActivityLog::select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');
        $users = User::whereIn('id', ActivityLog::select('user_id')->distinct()->pluck('user_id')->filter()->all())
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $total = ActivityLog::count();

        return view('audit.index', compact('logs', 'actions', 'entities', 'users', 'total'));
    }
}
