<?php

namespace App\Support;

class PhoneNumber
{
    // ✅ SYRIA CONFIGURATION
    // National Significant Number (NSN) for Syria Mobile is 9 digits (9XXXXXXXX)
    // Examples: 999239151, 933123456, 944123456
    public const COUNTRY_CODE = '963';
    public const COUNTRY_DIAL_PREFIX = '0'; // The leading '0' used domestically
    public const EXPECTED_LENGTH = 9;       // <--- CRITICAL FIX: 9 NOT 10

    /**
     * Normalize ANY input to strict E.164: +9639XXXXXXXX
     * Handles: "09...", "9...", "+9639...", "009639...", "+96309..." (user error)
     */
    public static function normalize(string $input): ?string
    {
        // 1. Strip everything except digits and leading +
        $clean = preg_replace('/[^\d+]/', '', $input);

        // 2. Handle International Prefix (+ or 00)
        if (str_starts_with($clean, '+')) {
            $clean = substr($clean, 1);
        } elseif (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        // 3. Strip Country Code if present (963)
        if (str_starts_with($clean, self::COUNTRY_CODE)) {
            $clean = substr($clean, strlen(self::COUNTRY_CODE));
        }

        // 4. Strip National Trunk Prefix (0) if present
        // This handles cases like: User typed "+9630999..." -> stripped to "0999..." -> strip 0 -> "999..."
        if (str_starts_with($clean, self::COUNTRY_DIAL_PREFIX)) {
            $clean = substr($clean, 1);
        }

        // 5. Validate: Must be exactly 9 digits for Syria
        if (strlen($clean) !== self::EXPECTED_LENGTH || !ctype_digit($clean)) {
            return null;
        }

        // 6. Must start with 9 (Syria Mobile Prefixes: 93, 94, 95, 96, 98, 99)
        if ($clean[0] !== '9') {
            return null; // Not a valid Syrian mobile prefix
        }

        // 7. Return Perfect E.164
        return '+' . self::COUNTRY_CODE . $clean;
    }

    /**
     * Get National Format for UI Display: "09XXXXXXXX" or "9XXXXXXXX"
     * Frontend expects this for the "Resend OTP" screen.
     */
    public static function getNationalNumber(string $e164): string
    {
        $prefix = '+' . self::COUNTRY_CODE;
        if (str_starts_with($e164, $prefix)) {
            $national = substr($e164, strlen($prefix)); // "999239151"
            // Return with leading zero for user-friendly display: "0999239151"
            return self::COUNTRY_DIAL_PREFIX . $national;
        }
        return $e164;
    }
}
