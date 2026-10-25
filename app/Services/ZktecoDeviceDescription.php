<?php

namespace App\Services;

use App\Models\ZktecoDevice;
use Illuminate\Http\Request;

/**
 * Reads what a device says about itself out of whatever it happens to send.
 *
 * ZKTeco firmwares differ in where they put this — some in the query string of
 * the handshake, some in the body of an upload that is not attendance, some
 * split across both — and they differ in whether the keys carry a leading
 * tilde. So nothing here looks for a particular table or a particular request:
 * it takes any key=value pairs it can find, keeps them all, and pulls the few
 * that have an obvious meaning into their own columns.
 *
 * Keeping the lot matters more than naming it. A firmware we have not seen can
 * report a capability under a name nobody here predicted, and the whole reason
 * this exists is that we had thrown that away for a year.
 */
class ZktecoDeviceDescription
{
    /** Keys worth a column of their own, lower-cased, tilde stripped. */
    private const KNOWN = [
        'devicename' => 'model',
        'deviceid' => null,
        'firmver' => 'firmware',
        'fwversion' => 'firmware',
        'platform' => 'platform',
    ];

    /**
     * Anything that looks like a description is recorded; anything else is
     * left alone. Returns true when the device told us something new.
     */
    public function absorb(Request $request, ZktecoDevice $device): bool
    {
        $pairs = array_merge(
            $this->pairsFrom((string) $request->getQueryString()),
            $this->pairsFrom((string) $request->getContent())
        );

        // SN and table are routing, not description — every request has them
        // and they would drown the useful keys.
        unset($pairs['sn'], $pairs['table'], $pairs['stamp'], $pairs['opstamp'], $pairs['options']);

        if ($pairs === []) {
            return false;
        }

        $known = [];

        foreach (self::KNOWN as $key => $column) {
            if ($column && isset($pairs[$key]) && $pairs[$key] !== '') {
                $known[$column] = mb_substr($pairs[$key], 0, 190);
            }
        }

        // Merged, not replaced: a firmware that reports its capabilities in
        // one request and its name in the next should end up with both.
        $capabilities = array_merge((array) ($device->capabilities ?? []), $pairs);

        $device->forceFill($known + [
            'capabilities' => $capabilities,
            'described_at' => now(),
        ])->saveQuietly();

        return true;
    }

    /**
     * key=value pairs out of a blob, whether separated by newlines, commas or
     * ampersands. Tildes are stripped because firmwares disagree about them
     * and nobody looking at this later cares.
     */
    private function pairsFrom(string $blob): array
    {
        if (trim($blob) === '') {
            return [];
        }

        // Attendance rows are tab-separated with no "=" in the first field.
        // They are handled elsewhere and must never be mistaken for this.
        if (! str_contains($blob, '=')) {
            return [];
        }

        $pairs = [];

        foreach (preg_split('/[\r\n,&]+/', $blob) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = strtolower(trim(ltrim(trim($key), '~')));
            $value = trim(urldecode($value));

            if ($key === '' || mb_strlen($key) > 60) {
                continue;
            }

            $pairs[$key] = mb_substr($value, 0, 190);
        }

        return $pairs;
    }

    /**
     * Whether this device reads cards, as far as it has told us.
     *
     * Three answers, not two. "We have not been told" is the honest state for
     * a device that has not re-registered since this started recording, and
     * calling that "no" would be the same mistake as calling a silent device
     * an idle one.
     */
    public static function readsCards(ZktecoDevice $device): ?bool
    {
        $caps = (array) ($device->capabilities ?? []);

        foreach (['rfcardon', 'iscardmachine', 'cardfunon', 'mifarefunon', 'isonlyrfmachine'] as $key) {
            if (array_key_exists($key, $caps)) {
                return (string) $caps[$key] !== '0';
            }
        }

        return null;
    }
}
