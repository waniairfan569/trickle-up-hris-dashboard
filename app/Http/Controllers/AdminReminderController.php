<?php

namespace App\Http\Controllers;

use App\Models\AdminReminderSetting;
use App\Services\AdminReminders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/** Admin reminders — live WFH-tomorrow / late-today lists + super-admin config. */
class AdminReminderController extends Controller
{
    public function index(AdminReminders $reminders)
    {
        return view('admin.reminders', [
            'settings' => AdminReminderSetting::getSettings(),
            'wfhTomorrow' => $reminders->wfhOn(),
            'lateToday' => $reminders->lateOn(),
            'canManage' => optional(auth()->user())->hasRole('super_admin'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        abort_unless(optional(auth()->user())->hasRole('super_admin'), 403, 'Only a super admin can change reminder settings.');

        $data = $request->validate([
            'wfh_enabled' => 'nullable|boolean',
            'wfh_send_time' => 'required|date_format:H:i',
            'late_enabled' => 'nullable|boolean',
            'late_send_time' => 'required|date_format:H:i',
        ]);

        AdminReminderSetting::getSettings()->update([
            'wfh_enabled' => $request->boolean('wfh_enabled'),
            'wfh_send_time' => $data['wfh_send_time'],
            'late_enabled' => $request->boolean('late_enabled'),
            'late_send_time' => $data['late_send_time'],
        ]);

        return redirect()->route('admin.reminders')->with('success', 'Reminder settings saved.');
    }

    /** Send today's reminders to all admins right now (preview / manual run). */
    public function sendNow()
    {
        abort_unless(optional(auth()->user())->hasRole('super_admin'), 403);

        Artisan::call('reminders:admin-daily', ['--force' => true]);

        return redirect()->route('admin.reminders')->with('success', 'Reminders sent to admins now (where there was anyone to report).');
    }
}
