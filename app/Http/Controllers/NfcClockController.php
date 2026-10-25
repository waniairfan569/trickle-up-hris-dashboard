<?php

namespace App\Http\Controllers;

use App\Models\QrClockPoint;
use App\Services\AttendanceService;
use App\Services\GeofenceService;
use App\Services\TimezoneService;
use Illuminate\Http\Request;

/**
 * Clocking in by touching a phone to a sticker.
 *
 * A tag holds one fixed URL for as long as it is stuck to the wall, so unlike
 * the scanned code it proves nothing by itself — anybody who has been to the
 * site once could type it in from home. The proof is swapped: the phone has
 * to be standing inside the geofence of the place the sticker is at.
 *
 * That swap is the point of the whole feature. A site with a sticker needs no
 * screen, no power, no network and no device, which is the difference between
 * an agency putting a clock-in point at one office and at thirty client sites
 * it visits.
 *
 * Distance is measured against the point's own office, not against the offices
 * the employee is assigned to. A cleaning company's staff are not on the books
 * of every building they clean, and the question being asked is "are you where
 * this sticker is", not "are you somewhere you usually are".
 *
 * Attendance itself is written by the same service a manual clock-in uses, so
 * re-clocking, breaks and late marks behave identically however somebody
 * arrives.
 */
class NfcClockController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private GeofenceService $geofence,
    ) {}

    /** The page the tag opens. */
    public function tap(Request $request, QrClockPoint $point, string $token)
    {
        if (! $point->is_active || ! $point->acceptsTapToken($token)) {
            return view('qr-clock.result', [
                'ok' => false,
                'point' => $point,
                'message' => 'This tag is not in use. Ask HR where to clock in.',
            ]);
        }

        $employee = $request->user();

        $today = \App\Models\AttendanceRecord::where('user_id', $employee->id)
            ->whereDate('date', app(TimezoneService::class)->toUserTime(now(), $employee)->toDateString())
            ->first();

        return view('nfc-clock.tap', [
            'point' => $point,
            'token' => $token,
            'employee' => $employee,
            'record' => $today,
            'action' => ($today && $today->clock_in && ! $today->clock_out) ? 'out' : 'in',
        ]);
    }

    public function punch(Request $request, QrClockPoint $point, string $token)
    {
        if (! $point->is_active || ! $point->acceptsTapToken($token)) {
            return $this->refuse($point, 'This tag is not in use. Ask HR where to clock in.');
        }

        // Required, not optional. A tap with no location is a tap that could
        // have come from anywhere, and there is nothing else here to trust.
        $validated = $request->validate([
            'action' => 'required|in:in,out',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ], [
            'lat.required' => 'Location is needed to clock in here.',
            'lng.required' => 'Location is needed to clock in here.',
        ]);

        $office = $point->officeLocation;

        if (! $point->canAcceptTap()) {
            return $this->refuse($point, 'This tag has no location set against it, so it cannot be trusted yet. Tell HR.');
        }

        $distance = $this->geofence->calculateDistance(
            (float) $validated['lat'],
            (float) $validated['lng'],
            (float) $office->latitude,
            (float) $office->longitude,
        );

        $radius = (int) ($office->radius_meters ?: 100);

        if ($distance > $radius) {
            // The number is given because the alternative is an employee who
            // is genuinely on site being told "no" with no way to tell whether
            // the phone is wrong, the radius is tight, or they are at the
            // wrong door.
            return $this->refuse($point, sprintf(
                'You appear to be about %s from %s. Clocking in here only works on site — move closer and tap again, or tell HR if this looks wrong.',
                $distance > 1000 ? round($distance / 1000, 1) . ' km' : $distance . ' m',
                $office->name
            ));
        }

        $options = [
            'ip' => $request->ip(),
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
            // Kept apart from 'qr': the two prove presence differently, and a
            // row should be able to say which of the two was trusted.
            'source' => 'nfc',
        ];

        try {
            $record = $validated['action'] === 'out'
                ? $this->attendance->clockOut($request->user(), $options)
                : $this->attendance->clockIn($request->user(), $options);
        } catch (\Throwable $e) {
            return $this->refuse($point, $e->getMessage());
        }

        $point->forceFill(['last_used_at' => now()])->saveQuietly();

        $at = $validated['action'] === 'out' ? $record->clock_out : $record->clock_in;

        return view('qr-clock.result', [
            'ok' => true,
            'point' => $point,
            'record' => $record,
            'message' => ($validated['action'] === 'out' ? 'Clocked out at ' : 'Clocked in at ')
                . app(TimezoneService::class)->toUserTime($at, $request->user())->format('H:i'),
        ]);
    }

    /**
     * Turning tap on or off for a point, and with it the sticker's URL.
     *
     * Refused where there is no place to measure against, because a tap point
     * without a geofence would accept anybody from anywhere — which is worse
     * than having no tap at all, and would be discovered months later.
     */
    public function toggle(QrClockPoint $point)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        if (! $point->accepts_tap && ! $point->canAcceptTap()) {
            return back()->with('error',
                'Give this point an office location with coordinates first — a tap is only trusted because of where the phone is.');
        }

        $point->forceFill(['accepts_tap' => ! $point->accepts_tap])->save();

        return back()->with('success', $point->accepts_tap
            ? 'Tap turned on. Write the link below to an NFC tag and stick it up.'
            : 'Tap turned off. Any tag already written for this point stops working.');
    }

    private function refuse(QrClockPoint $point, string $message)
    {
        return view('qr-clock.result', ['ok' => false, 'point' => $point, 'message' => $message]);
    }
}
