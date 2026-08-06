<?php

namespace App\Http\Controllers;

use App\Support\NotificationCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    /** The employee's personal settings page. */
    public function index(Request $request)
    {
        $tzList = app(\App\Services\TimezoneService::class)->getTimezoneList();

        return view('settings.index', [
            'user'   => $request->user(),
            'tzList' => $tzList,
        ]);
    }

    /** Notifications: per-category Email / In-app toggles. */
    public function updateNotifications(Request $request)
    {
        $user = $request->user();
        $submitted = (array) $request->input('prefs', []);

        $prefs = [];
        foreach (array_keys(NotificationCategories::CATEGORIES) as $category) {
            $mandatoryEmail = in_array($category, NotificationCategories::MANDATORY_EMAIL, true);
            $prefs[$category] = [
                // Checkbox present in POST => on. Mandatory email is always on.
                'mail'     => $mandatoryEmail ? true : (bool) ($submitted[$category]['mail'] ?? false),
                'database' => (bool) ($submitted[$category]['database'] ?? false),
            ];
        }

        $user->update(['notification_prefs' => $prefs]);

        return back()->with('success', 'Notification preferences saved.')->with('tab', 'notifications');
    }

    /** Appearance: theme (light / dark / system). */
    public function updateAppearance(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        $request->user()->update(['theme' => $data['theme']]);

        return back()->with('success', 'Appearance updated.')->with('tab', 'appearance');
    }

    /** Security: change own password. */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
        ]);

        $request->user()->update([
            'password'             => Hash::make($request->input('password')),
            'must_change_password' => false,
        ]);

        return back()->with('success', 'Password changed successfully.')->with('tab', 'security');
    }

    /** Preferences: timezone, date format, week start. */
    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'timezone'    => ['nullable', 'string', 'max:64'],
            'date_format' => ['nullable', Rule::in(['d M Y', 'M d, Y', 'd/m/Y', 'm/d/Y', 'Y-m-d'])],
            'week_start'  => ['required', Rule::in(['monday', 'sunday'])],
        ]);

        $user = $request->user();

        // Empty timezone = follow the company default (turn custom off).
        if (empty($data['timezone'])) {
            $user->use_custom_timezone = false;
        } else {
            $user->timezone = $data['timezone'];
            $user->use_custom_timezone = true;
        }
        $user->date_format = $data['date_format'] ?: null;
        $user->week_start  = $data['week_start'];
        $user->save();

        return back()->with('success', 'Preferences saved.')->with('tab', 'preferences');
    }
}
