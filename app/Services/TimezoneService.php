<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;

/**
 * Centralises all timezone handling for the HRIS.
 *
 * Storage model: timestamps are persisted in the application's *canonical*
 * timezone (config('app.timezone')). This service converts between that
 * canonical timezone and each employee's effective display timezone.
 *
 * Keeping the canonical timezone in config (rather than hard-coding 'UTC')
 * means this code is correct for the current setup AND keeps working unchanged
 * if APP_TIMEZONE is ever switched to UTC.
 */
class TimezoneService
{
    public const FALLBACK_TIMEZONE = 'Asia/Karachi';

    /**
     * The timezone the database stores timestamps in.
     */
    public function canonicalTimezone(): string
    {
        return config('app.timezone') ?: self::FALLBACK_TIMEZONE;
    }

    /**
     * Resolve the timezone that should be used to display this user's times.
     * Custom override wins; otherwise inherit the company entity timezone.
     */
    public function getEffectiveTimezone(?User $user): string
    {
        if (!$user) {
            return $this->canonicalTimezone();
        }

        if ($user->use_custom_timezone && !empty($user->timezone)) {
            return $user->timezone;
        }

        return optional($user->companyEntity)->timezone ?: self::FALLBACK_TIMEZONE;
    }

    /**
     * All PHP timezones grouped by region for a <select>, e.g.
     *   ['Asia' => ['Asia/Karachi' => 'Karachi (UTC+05:00)', ...], ...]
     */
    public function getTimezoneList(): array
    {
        $grouped = [];
        $utc = new DateTimeZone('UTC');
        $reference = new DateTime('now', $utc);

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $parts = explode('/', $identifier, 2);
            $region = count($parts) === 2 ? $parts[0] : 'Other';
            $city = count($parts) === 2 ? str_replace('_', ' ', $parts[1]) : $identifier;

            $offset = (new DateTimeZone($identifier))->getOffset($reference);
            $grouped[$region][$identifier] = sprintf('%s (%s)', $city, $this->formatOffset($offset));
        }

        ksort($grouped);
        foreach ($grouped as &$zones) {
            asort($zones);
        }

        return $grouped;
    }

    /**
     * Convert a canonical-stored time into the user's display timezone.
     */
    public function toUserTime(Carbon $time, ?User $user): Carbon
    {
        return $time->copy()->setTimezone($this->getEffectiveTimezone($user));
    }

    /**
     * Convert an arbitrary Carbon instance into the canonical storage timezone.
     * (Named toUtc for parity with the original spec; it targets the canonical
     * timezone, which equals UTC if APP_TIMEZONE is set to UTC.)
     */
    public function toUtc(Carbon $time, ?User $user = null): Carbon
    {
        return $time->copy()->setTimezone($this->canonicalTimezone());
    }

    /**
     * Convert a wall-clock time that is known to be in the user's timezone into
     * the canonical storage timezone. Use this when you have a naive local time
     * (e.g. a value typed by the user) that must be stored.
     */
    public function fromUserTimeToStorage(Carbon $localTime, ?User $user): Carbon
    {
        return $localTime->copy()
            ->setTimezone($this->getEffectiveTimezone($user))
            ->setTimezone($this->canonicalTimezone());
    }

    /**
     * Format a canonical-stored time for display in the user's timezone.
     * Defaults to the company entity's configured time format.
     */
    public function formatForUser(?Carbon $time, ?User $user, ?string $format = null): string
    {
        if (!$time) {
            return '—';
        }

        $format = $format
            ?? optional(optional($user)->companyEntity)->time_format
            ?? 'h:i A';

        return $this->toUserTime($time, $user)->format($format);
    }

    /**
     * Format a canonical-stored time using the company entity's date format
     * (or a combined date + time format).
     */
    public function formatDateForUser(?Carbon $time, ?User $user, bool $withTime = false): string
    {
        if (!$time) {
            return '—';
        }

        $entity = optional($user)->companyEntity;
        $dateFormat = optional($entity)->date_format ?? 'd M Y';
        $timeFormat = optional($entity)->time_format ?? 'h:i A';
        $format = $withTime ? $dateFormat . ' ' . $timeFormat : $dateFormat;

        return $this->toUserTime($time, $user)->format($format);
    }

    /**
     * The current time in the user's effective timezone.
     */
    public function getCurrentTimeForUser(?User $user): Carbon
    {
        return Carbon::now($this->getEffectiveTimezone($user));
    }

    /**
     * The short abbreviation for a user's timezone, e.g. "PKT", "BST".
     */
    public function abbreviation(?User $user): string
    {
        return $this->getCurrentTimeForUser($user)->format('T');
    }

    private function formatOffset(int $offsetSeconds): string
    {
        $sign = $offsetSeconds < 0 ? '-' : '+';
        $abs = abs($offsetSeconds);
        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);

        return sprintf('UTC%s%02d:%02d', $sign, $hours, $minutes);
    }
}
