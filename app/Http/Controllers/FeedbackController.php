<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackRespondedNotification;
use App\Notifications\FeedbackSubmittedNotification;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    // ---- Employee -----------------------------------------------------------

    /** An employee submits feedback or reports an issue. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:' . implode(',', array_keys(Feedback::CATEGORIES)),
            'subject' => 'nullable|string|max:150',
            'message' => 'required|string|max:3000',
        ], [
            'message.required' => 'Please describe your feedback or the issue.',
        ]);

        $feedback = Feedback::create($validated + [
            'user_id' => auth()->id(),
            'company_id' => auth()->user()->company_id,
            'status' => 'open',
        ]);

        foreach ($this->admins() as $admin) {
            try {
                $admin->notify(new FeedbackSubmittedNotification($feedback));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Thanks — your feedback has been sent to HR.');
    }

    // ---- Admin --------------------------------------------------------------

    /** Admin: every submission, filterable by status. */
    public function adminIndex(Request $request)
    {
        $this->authorizeAdmin($request);

        $status = in_array($request->get('status'), ['open', 'in_progress', 'resolved'], true)
            ? $request->get('status') : null;

        $feedback = Feedback::with(['user', 'responder'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)->withQueryString();

        $counts = [
            'open' => Feedback::where('status', 'open')->count(),
            'in_progress' => Feedback::where('status', 'in_progress')->count(),
            'resolved' => Feedback::where('status', 'resolved')->count(),
        ];

        return view('feedback.admin', compact('feedback', 'counts', 'status'));
    }

    /** Admin: reply and/or change the status. */
    public function respond(Request $request, Feedback $feedback)
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'admin_response' => 'nullable|string|max:3000',
            'status' => 'required|in:open,in_progress,resolved',
        ]);

        $reply = trim((string) ($validated['admin_response'] ?? ''));
        $hasNewReply = $reply !== '' && $reply !== (string) $feedback->admin_response;

        $feedback->update([
            'admin_response' => $reply !== '' ? $reply : $feedback->admin_response,
            'status' => $validated['status'],
            'responded_by' => $hasNewReply ? auth()->id() : $feedback->responded_by,
            'responded_at' => $hasNewReply ? now() : $feedback->responded_at,
        ]);

        if ($hasNewReply && $feedback->user) {
            try {
                $feedback->user->notify(new FeedbackRespondedNotification($feedback));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Response saved' . ($hasNewReply ? ' — the employee has been notified.' : '.'));
    }

    private function admins()
    {
        return User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
              ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403, 'Only HR administrators can manage feedback.');
    }
}
