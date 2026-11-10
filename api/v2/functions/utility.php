<?php

declare(strict_types=1);

/**
 * Small string and identifier helpers shared by API v2 handlers.
 */

/**
 * Sanitize a string
 *
 * @param string $string
 * @return string
 */
if (!function_exists('sanitize_string')) {
    function sanitize_string(string $string): string
    {
        return strip_tags(htmlspecialchars($string, ENT_QUOTES, 'UTF-8'));
    }
}

/**
 * Generate a random string
 *
 * @param int $length
 * @return string
 */
if (!function_exists('random_string')) {
    function random_string(int $length = 10): string
    {
        return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }
}

/**
 * Generate a random number
 *
 * @param int $length
 * @return string
 */
if (!function_exists('random_number')) {
    function random_number(int $length = 10): string
    {
        return substr(str_shuffle('0123456789'), 0, $length);
    }
}

/**
 * Partially hide email
 *
 * @param string $email
 * @return string
 */
if (!function_exists('partially_hide_email')) {
    function partially_hide_email(string $email): string
    {
        $em = explode('@', $email);
        $name = implode('@', array_slice($em, 0, count($em) - 1));
        $len = (int) floor(strlen($name) / 2);
        return substr($name, 0, $len) . str_repeat('*', $len) . '@' . end($em);
    }
}

/**
 * Port of v1 GeneralClass::convertToUtf8 — coerces legacy ISO-8859-1 strings
 * to UTF-8 so JSON-encoded responses do not break on accented characters.
 *
 * @param string|null $string
 * @return string
 */
if (!function_exists('convert_to_utf8')) {
    function convert_to_utf8(?string $string): string
    {
        return mb_convert_encoding($string ?? '', 'UTF-8', 'ISO-8859-1');
    }
}

/**
 * Port of v1 GeneralClass::cleanPhoneString — strips formatting from a phone
 * number, with optional `+1` US country-code prefix.
 *
 * @param string $phone
 * @param bool   $country_code
 * @return string
 */
if (!function_exists('clean_phone_string')) {
    function clean_phone_string(string $phone, bool $country_code = false): string
    {
        $phone = str_replace('+01', '', $phone);
        $phone = str_replace('+1', '', $phone);
        $string = str_replace(' ', '-', $phone);
        $new_phone = preg_replace('/[^A-Za-z0-9\+]/', '', $string) ?? '';
        return $country_code ? '+1' . $new_phone : $new_phone;
    }
}

/**
 * Port of v1 GeneralClass::formatPhoneNumber — strips a `+1` prefix and formats
 * a 10-digit US phone number as `(XXX) XXX-XXXX`. Returns the input unchanged
 * if it isn't exactly 10 digits after stripping.
 *
 * @param string $phone
 * @return string
 */
if (!function_exists('format_phone_number')) {
    function format_phone_number(string $phone): string
    {
        $phone = str_replace('+1', '', $phone);
        if (strlen($phone) !== 10) {
            return $phone;
        }
        $parts = str_split($phone, 3);
        return '(' . $parts[0] . ') ' . $parts[1] . '-' . $parts[2] . ($parts[3] ?? '');
    }
}

/**
 * Port of v1 GeneralClass::usPhoneNumber — formats an exactly-10-digit phone
 * number as `XXX-XXX-XXXX`. Returns the input unchanged if it doesn't match.
 *
 * @param string $number
 * @return string
 */
if (!function_exists('us_phone_number')) {
    function us_phone_number(string $number): string
    {
        return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3', $number) ?? $number;
    }
}

/**
 * Port of v1 GeneralClass::converToTz — converts a datetime string from one
 * timezone to another.
 *
 * @param string $time   Datetime string parseable by DateTime.
 * @param string $toTz   Target IANA timezone.
 * @param string $fromTz Source IANA timezone.
 * @param string $format DateTime::format() string for the result.
 * @return string
 */
if (!function_exists('convert_tz')) {
    function convert_tz(string $time, string $toTz, string $fromTz, string $format = 'm/d/Y g:i a'): string
    {
        $date = new \DateTime($time, new \DateTimeZone($fromTz));
        $date->setTimezone(new \DateTimeZone($toTz));
        return $date->format($format);
    }
}
/**
 * 10-digit form used by whitelist_phone_number and v1 SMS checks.
 * Matches v1 cleanPhoneString() after the 999-999-9999 input mask, plus a
 * leading country-code 1 when the caller pasted 11 digits.
 */
if (!function_exists('normalize_whitelist_phone')) {
    function normalize_whitelist_phone(string $phone): string
    {
        $normalized = clean_phone_string($phone);
        if (strlen($normalized) === 11 && $normalized[0] === '1') {
            $normalized = substr($normalized, 1);
        }
        return $normalized;
    }
}

/**
 * Lowercase alphanumeric-only form of a label, for comparing values that differ only by
 * case or separators (e.g. 'cloud_rx', 'Cloud-Rx', 'Cloud Rx' and 'cloudrx' all become 'cloudrx').
 *
 * @param string $value Label such as a platform or provider key
 * @return string Lowercase letters and digits only; empty when nothing remains
 */
if (!function_exists('normalize_key')) {
    function normalize_key(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($value));
    }
}