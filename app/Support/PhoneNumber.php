<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize any Syrian/KSA/Generic input to E.164 (+9639XXXXXXXX).
     * Adjust COUNTRY_CODE / LENGTH constants for your target country.
     */
    public const COUNTRY_CODE = '963'; // Syria
    public const COUNTRY_DIAL_PREFIX = '0'; // Leading zero to strip
    public const EXPECTED_LENGTH = 10; // 9XXXXXXXX (without leading 0 or country code)

    /**
     * Convert input like "09xxxxxxx", "9xxxxxxx", "+9639xxxxxxx", "009639xxxxxxx"
     * to "+9639xxxxxxx".
     */
    public static function normalize(string $input): ?string
    {
        // 1. Strip spaces, dashes, parentheses
        $clean = preg_replace('/[\s\-\(\)]/', '', $input);

        // 2. Handle + or 00 prefix
        if (str_starts_with($clean, '+')) {
            $clean = substr($clean, 1);
        } elseif (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        // 3. Strip leading national dialing prefix (0)
        if (str_starts_with($clean, self::COUNTRY_DIAL_PREFIX)) {
            $clean = substr($clean, 1);
        }

        // 4. Strip country code if present
        if (str_starts_with($clean, self::COUNTRY_CODE)) {
            $clean = substr($clean, strlen(self::COUNTRY_CODE));
        }

        // 5. Validate remaining length (National Significant Number)
        if (strlen($clean) !== self::EXPECTED_LENGTH || !ctype_digit($clean)) {
            return null; // Invalid format
        }

        // 6. Return E.164
        return '+' . self::COUNTRY_CODE . $clean;
    }

    /**
     * Get the raw national number (9XXXXXXXX) for DB storage if you prefer not storing +.
     * But for SMS Gate, ALWAYS send E.164.
     */
    public static function getNationalNumber(string $e164): string
    {
        return substr($e164, strlen('+' . self::COUNTRY_CODE));
    }
}
