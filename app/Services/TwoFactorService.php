<?php

namespace App\Services;

/**
 * Minimal RFC 6238 TOTP + recovery codes — no external dependency.
 * Compatible with Google Authenticator, Authy, 1Password, etc.
 */
class TwoFactorService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // base32
    private const PERIOD = 30;
    private const DIGITS = 6;

    /** A fresh base32 secret. */
    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    /** otpauth:// URI for the QR code. */
    public function otpauthUri(string $secret, string $account, string $issuer = 'Trickle Hub'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** Verify a 6-digit code against the secret (±1 window for clock drift). */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $slice = (int) floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->codeAt($secret, $slice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Eight one-time recovery codes (format XXXX-XXXX). */
    public function recoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        }

        return $codes;
    }

    // ---- internals ----------------------------------------------------------

    private function codeAt(string $secret, int $slice): string
    {
        $key = $this->base32Decode($secret);
        $bin = pack('N*', 0) . pack('N*', $slice); // 8-byte big-endian counter
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;

        $part = ((ord($hash[$offset]) & 0x7f) << 24)
              | ((ord($hash[$offset + 1]) & 0xff) << 16)
              | ((ord($hash[$offset + 2]) & 0xff) << 8)
              | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($part % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = rtrim(strtoupper($secret), '=');
        $buffer = 0;
        $bitsLeft = 0;
        $out = '';

        foreach (str_split($secret) as $char) {
            $val = strpos(self::ALPHABET, $char);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $out .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }

        return $out;
    }
}
