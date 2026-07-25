<?php

namespace App\Services;

/**
 * Self-contained TOTP (RFC 6238) generator/verifier, compatible with
 * standard authenticator apps (Google Authenticator, Authy, etc.).
 *
 * No external package required: HMAC-SHA1-based one-time codes only need
 * PHP's native hash_hmac(), which keeps this dependency-free.
 */
class TwoFactorAuthenticator
{
    private const SECRET_BYTES = 20;

    private const PERIOD_SECONDS = 30;

    private const DIGITS = 6;

    public function generateSecretKey(): string
    {
        return $this->base32Encode(random_bytes(self::SECRET_BYTES));
    }

    public function getQrCodeUrl(string $issuer, string $accountName, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($accountName),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD_SECONDS
        );
    }

    /**
     * Verify a code, tolerating clock drift of $window time-steps on either side.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);

        if (! ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $timeSlice = (int) floor(time() / self::PERIOD_SECONDS);

        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->generateCode($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateCode(string $secret, int $timeSlice): string
    {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;

        $truncated =
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $base32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper(rtrim($base32, '='));
        $binary = '';

        foreach (str_split($base32) as $char) {
            $pos = strpos($alphabet, $char);

            if ($pos === false) {
                continue;
            }

            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }

        return $bytes;
    }
}
