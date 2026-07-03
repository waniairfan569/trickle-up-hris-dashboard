<?php

namespace App\Http\Controllers;

use App\Models\CodeRequest;
use App\Models\User;
use App\Notifications\CodeProvidedNotification;
use App\Notifications\CodeRequestedNotification;
use Illuminate\Http\Request;

class CodeRequestController extends Controller
{
    // ---- Employee -----------------------------------------------------------

    /** AJAX: employee fires off a quick request for a login code. */
    public function quickRequest(Request $request)
    {
        $validated = $request->validate([
            'tool_name' => 'required|string|max:100',
            'message' => 'nullable|string|max:255',
        ]);

        $codeRequest = CodeRequest::create([
            'employee_id' => $request->user()->id,
            'tool_name' => trim($validated['tool_name']),
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
        ]);

        // Ping every HR / super admin (bell + email).
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
              ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new CodeRequestedNotification($codeRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'request_id' => $codeRequest->id,
            'message' => 'Request sent! HR will share the code shortly.',
        ]);
    }

    /** Employee: my recent code requests (with any codes received). */
    public function myCodeRequests(Request $request)
    {
        $requests = CodeRequest::where('employee_id', $request->user()->id)
            ->with('responder')
            ->latest()
            ->take(20)
            ->get();

        return view('code-requests.my', compact('requests'));
    }

    // ---- Admin --------------------------------------------------------------

    /** Admin: the quick-response panel. */
    public function pendingCodes(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $pending = CodeRequest::pending()->with('employee')->oldest()->get();
        $resolved = CodeRequest::where('status', 'code_sent')
            ->with(['employee', 'responder'])
            ->latest('code_sent_at')
            ->take(30)
            ->get();

        return view('code-requests.pending', compact('pending', 'resolved'));
    }

    /** AJAX: HR sends the code back to the employee. */
    public function sendCode(Request $request, CodeRequest $codeRequest)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'code_provided' => 'required|string|max:100',
            'code_expires_note' => 'nullable|string|max:100',
        ]);

        $codeRequest->update([
            'code_provided' => trim($validated['code_provided']),
            'code_expires_note' => $validated['code_expires_note'] ?? null,
            'status' => 'code_sent',
            'code_sent_at' => now(),
            'responded_by' => $request->user()->id,
        ]);

        if ($codeRequest->employee) {
            try {
                $codeRequest->employee->notify(new CodeProvidedNotification($codeRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Sent to ' . (optional($codeRequest->employee)->full_name ?? 'employee') . '.',
        ]);
    }
}
