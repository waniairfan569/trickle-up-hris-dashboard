<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

class InvitationController extends Controller
{
    protected InvitationService $invitationService;

    public function __construct(InvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    public function showAcceptForm(string $token)
    {
        try {
            $employee = $this->invitationService->validateToken($token);
            return view('invitation.accept', compact('employee', 'token'));
        } catch (InvalidArgumentException $e) {
            return view('invitation.invalid', ['reason' => 'invalid', 'message' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            return view('invitation.invalid', ['reason' => 'expired', 'message' => $e->getMessage()]);
        } catch (LogicException $e) {
            return redirect()->route('login')->with('status', 'Your account is already set up. Please log in.');
        }
    }

    public function accept(Request $request, string $token)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()],
        ]);

        try {
            $user = $this->invitationService->acceptInvitation($token, $request->password);
            
            Auth::login($user);
            
            return redirect()->route('dashboard')->with('success', "Welcome to " . config('app.name') . ", {$user->first_name}! Your account is now active.");
            
        } catch (InvalidArgumentException | RuntimeException | LogicException $e) {
            return redirect()->route('invitation.accept', ['token' => $token])->withErrors(['token' => $e->getMessage()]);
        }
    }

    // HR Actions (Requires Auth)

    public function resendInvitation(User $employee)
    {
        try {
            $this->invitationService->resendInvitation($employee, auth()->user());
            return back()->with('success', 'Invitation resent to ' . $employee->email);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updateEmailAndResend(Request $request, User $employee)
    {
        if ($employee->account_status !== 'invited') {
            return back()->with('error', 'Only pending invitations can be edited.');
        }

        $validated = $request->validate([
            'email' => [
                'required', 'email', 'max:255',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($employee->id),
            ],
        ]);

        try {
            $employee->update(['email' => $validated['email']]);
            $this->invitationService->resendInvitation($employee, auth()->user());

            return back()->with('success', 'Invitation updated and sent to ' . $employee->email);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelInvitation(User $employee)
    {
        try {
            $name = $employee->full_name;
            $this->invitationService->cancelInvitation($employee, auth()->user());
            return redirect()
                ->route('employees.pending-invitations')
                ->with('success', "Invitation for {$name} has been cancelled and removed.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
