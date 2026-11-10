<?php

declare(strict_types=1);

namespace Utils;

/**
 * HS256 JWT encode/decode — no external dependencies.
 *
 * @param array{secret:string, ttl:int} $config secret required; ttl used by encode() when exp is omitted
 */
class Hs256Jwt
{
    private const ALGO = 'HS256';

    /**
     * @param array<string, mixed> $payload
     * @param array{secret:string, ttl:int} $config
     */
    public static function encode(array $payload, array $config): string
    {
        $now = time();
        if (!isset($payload['iat'])) {
            $payload['iat'] = $now;
        }
        if (!isset($payload['exp'])) {
            $payload['exp'] = $now + (int) $config['ttl'];
        }
        $header = ['typ' => 'JWT', 'alg' => self::ALGO];
        $segments = [];
        $segments[] = self::base64UrlEncode(json_encode($header));
        $segments[] = self::base64UrlEncode(json_encode($payload));
        $signingInput = implode('.', $segments);
        $signature = self::sign($signingInput, $config['secret']);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @param array{secret:string, ttl?:int} $config ttl is unused; kept for shared config shape with encode()
     * @return array<string, mixed>|null
     */
    public static function decode(string $jwt, array $config): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $header = json_decode(self::base64UrlDecode($parts[0]), true);
        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        if (!is_array($header) || !is_array($payload)) {
            return null;
        }
        if (empty($header['alg']) || $header['alg'] !== self::ALGO) {
            return null;
        }
        $expectedSig = self::sign($parts[0] . '.' . $parts[1], $config['secret']);
        $sig = self::base64UrlDecode($parts[2]);
        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private static function sign(string $msg, string $key): string
    {
        return hash_hmac('sha256', $msg, $key, true);
    }

    private static function base64UrlEncode(string $input): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($input));
    }

    private static function base64UrlDecode(string $input): string
    {
        $pad = 4 - strlen($input) % 4;
        if ($pad < 4) {
            $input .= str_repeat('=', $pad);
        }

        return base64_decode(strtr($input, '-_', '+/'), true) ?: '';
    }
}
