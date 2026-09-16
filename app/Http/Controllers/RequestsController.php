<?php

namespace App\Http\Controllers;

use App\Models\CodeRequest;
use App\Models\EquipmentRequest;
use App\Models\Feedback;

/**
 * Requests hub — one landing page with a tile per inbound request queue
 * (Equipment, Login codes, Feedback), so the sidebar carries a single
 * "Requests" link under Administration instead of its own section.
 * Admin-only, same as the pages it links to.
 */
class RequestsController extends Controller
{
    public function index()
    {
        $tiles = [];

        if (plan_allows('equipment')) {
            $pending = EquipmentRequest::pending()->count();
            $tiles[] = [
                'route' => 'equipment.admin',
                'icon' => 'package',
                'tone' => 'brand',
                'title' => 'Equipment Requests',
                'text' => 'Employees asking to take company equipment home — approve or decline.',
                'stat' => $pending,
                'stat_label' => 'pending',
                'meta' => EquipmentRequest::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count() . ' this month',
                'badge' => $pending,
            ];
        }

        if (plan_allows('code_requests')) {
            $pending = CodeRequest::pending()->count();
            $tiles[] = [
                'route' => 'code-requests.pending',
                'icon' => 'key-round',
                'tone' => 'amber',
                'title' => 'Code Requests',
                'text' => 'Employees waiting for a one-time login code to be sent.',
                'stat' => $pending,
                'stat_label' => 'waiting',
                'meta' => CodeRequest::whereDate('created_at', today())->count() . ' today',
                'badge' => $pending,
            ];
        }

        if (plan_allows('feedback')) {
            $open = Feedback::where('status', 'open')->count();
            $tiles[] = [
                'route' => 'feedback.admin',
                'icon' => 'message-square-heart',
                'tone' => 'rose',
                'title' => 'Feedback & Suggestions',
                'text' => 'Ideas and concerns from the team — triage, respond and resolve.',
                'stat' => $open,
                'stat_label' => 'open',
                'meta' => Feedback::where('status', 'in_progress')->count() . ' in progress',
                'badge' => $open,
            ];
        }

        return view('requests.index', compact('tiles'));
    }
}
