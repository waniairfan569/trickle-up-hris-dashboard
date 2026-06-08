<?php

namespace App\Services;

use App\Models\User;
use App\Models\InvitationLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmployeeInvitationMail;
use Illuminate\Support\Facades\Log;
use Exception;

class InvitationService
{
    /**
     * Generate a cryptographically secure random token.
     */
    public function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Send an invitation to an employee.
     */
    public function sendInvitation(User $employee, User $invitedBy): void
    {
        $rawToken = $this->generateToken();
        $expiresInHours = 72;

        $employee->invitation_token_hash = hash('sha256', $rawToken);
        $employee->invitation_sent_at = now();
        $employee->invitation_expires_at = now()->addHours($expiresInHours);
        $employee->account_status = 'invited';
        $employee->invited_by = $invitedBy->id;
        $employee->save();

        $signedUrl = route('invitation.accept', ['token' => $rawToken]);

        try {
            Mail::to($employee->email)->send(new EmployeeInvitationMail($employee, $invitedBy, $signedUrl, $expiresInHours));
            
            InvitationLog::create([
                'user_id' => $employee->id,
                'action' => 'sent',
                'performed_by' => $invitedBy->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send invitation email', [
                'user_id' => $employee->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to send invitation email. Please check mail configuration.');
        }
    }

    /**
     * Resend an invitation to an employee.
     */
    public function resendInvitation(User $employee, User $resentBy): void
    {
        if ($employee->account_status === 'active') {
            throw new Exception('Cannot resend invitation. The employee account is already active.');
        }

        // Invalidate old token just in case
        $employee->invitation_token_hash = null;
        $employee->save();

        // sendInvitation handles token generation and expiry setup
        $this->sendInvitation($employee, $resentBy);

        // Update the last log to 'resent' to be more precise
        $lastLog = $employee->invitationLogs()->latest()->first();
        if ($lastLog) {
            $lastLog->update(['action' => 'resent']);
        }
    }

    /**
     * Validate an invitation token and return the associated user.
     */
    public function validateToken(string $rawToken): User
    {
        $hashedToken = hash('sha256', $rawToken);
        
        $user = User::where('invitation_token_hash', $hashedToken)->first();

        if (!$user) {
            throw new \InvalidArgumentException('This invitation link is invalid.');
        }

        if ($user->account_status === 'active') {
            throw new \LogicException('Your account is already active. Please log in.');
        }

        if ($user->invitation_expires_at && $user->invitation_expires_at->isPast()) {
            InvitationLog::create([
                'user_id' => $user->id,
                'action' => 'expired',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            throw new \RuntimeException('This invitation link has expired. Please ask HR to resend your invitation.');
        }

        return $user;
    }

    /**
     * Accept an invitation and activate the user account.
     */
    public function acceptInvitation(string $rawToken, string $password): User
    {
        $user = $this->validateToken($rawToken);

        $user->password = Hash::make($password);
        $user->account_status = 'active';
        $user->invitation_accepted_at = now();
        $user->invitation_token_hash = null;
        $user->must_change_password = false;
        $user->email_verified_at = now();
        $user->save();

        InvitationLog::create([
            'user_id' => $user->id,
            'action' => 'accepted',
            'performed_by' => null, // The employee themselves
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $user;
    }

    /**
     * Set password immediately (Option B).
     */
    public function setPasswordNow(User $employee, string $password, User $setBy): void
    {
        $employee->password = Hash::make($password);
        $employee->account_status = 'active';
        $employee->must_change_password = true;
        $employee->invitation_token_hash = null;
        $employee->invitation_accepted_at = now();
        $employee->email_verified_at = now();
        $employee->save();

        InvitationLog::create([
            'user_id' => $employee->id,
            'action' => 'accepted',
            'performed_by' => $setBy->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(User $employee, User $cancelledBy): void
    {
        // Log the cancellation before deleting
        InvitationLog::create([
            'user_id' => $employee->id,
            'action' => 'cancelled',
            'performed_by' => $cancelledBy->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Delete the related employee profile row first (avoids orphaned records)
        \App\Models\Employee::where('user_id', $employee->id)->delete();

        // Delete the user record entirely — they never activated their account
        $employee->delete();
    }
}
