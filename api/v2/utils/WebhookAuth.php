<?php

declare(strict_types=1);

namespace Utils;

use Models\ServiceProviderConfigurationModel;
use Reunite\Framework\Cloud;
use Reunite\Logger;

class WebhookAuth
{
    /**
     * Verifies the vendor-specific HMAC or ECDSA signature on an inbound webhook request.
     *
     * Reads the raw request body from Cloud('raw_request_body') (cached in bootstrap.php).
     * On success, sets Cloud context: request_type=webhook, caller_id=vendor, api_scope=default.
     *
     * @param string $vendor Vendor key: 'authorize_net', 'twilio', 'sendgrid', or a custom key
     * @return bool True if the signature is valid; false if missing, malformed, or mismatched
     */
    public static function verify(string $vendor): bool
    {
        $rawBody = Cloud::get('raw_request_body') ?? '';

        $valid = match ($vendor) {
            'authorize_net' => self::verifyAuthorizeNet($rawBody),
            'twilio'        => self::verifyTwilio($rawBody),
            'sendgrid'      => self::verifySendGrid($rawBody),
            'telnyx'        => self::verifyTelnyx($rawBody),
            default         => self::verifyCustom($vendor, $rawBody),
        };

        if (!$valid) {
            Logger::warning('Webhook signature verification failed', ['vendor' => $vendor]);
            return false;
        }

        Cloud::set('request_type', 'webhook');
        Cloud::set('caller_id', $vendor);
        Cloud::set('api_scope', 'default');
        Database::setDbInstance();

        return true;
    }

    private static function verifyAuthorizeNet(string $rawBody): bool
    {
        $signature = $_SERVER['HTTP_X_ANET_SIGNATURE'] ?? '';
        $secret    = $_SERVER['AUTHORIZE_NET_SIGNATURE_KEY'] ?? '';

        if ($signature === '' || $secret === '') {
            return false;
        }

        $expected = 'sha512=' . strtoupper(hash_hmac('sha512', $rawBody, $secret));
        return hash_equals($expected, $signature);
    }

    private static function verifyTwilio(string $rawBody): bool
    {
        $signature = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '';
        $secret    = (string) ($_SERVER['WEBHOOK_SECRET_TWILIO'] ?? '');
        if ($secret === '') {
            $details = (new ServiceProviderConfigurationModel())->findConfigDetails('twilio');
            $secret = (string) ($details['TWILIO_AUTH_TOKEN'] ?? '');
            if ($secret === '') {
                $secret = (string) (config('TWILIO_AUTH_TOKEN') ?: '');
            }
        }

        if ($signature === '' || $secret === '') {
            return false;
        }

        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

        $params = [];
        parse_str($rawBody, $params);
        ksort($params);
        $data = $url . implode('', array_map(
            static fn(string $k, string $v): string => $k . $v,
            array_keys($params),
            array_values($params)
        ));

        $expected = base64_encode(hash_hmac('sha1', $data, $secret, true));
        return hash_equals($expected, $signature);
    }

    private static function verifySendGrid(string $rawBody): bool
    {
        $signature = $_SERVER['HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE'] ?? '';
        $timestamp  = $_SERVER['HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP'] ?? '';
        $publicKey  = $_SERVER['SENDGRID_WEBHOOK_PUBLIC_KEY'] ?? '';

        if ($signature === '' || $timestamp === '' || $publicKey === '') {
            return false;
        }

        $payload = $timestamp . $rawBody;

        $keyResource = openssl_pkey_get_public($publicKey);
        if ($keyResource === false) {
            return false;
        }

        $sigBin = base64_decode($signature, true);
        if ($sigBin === false) {
            return false;
        }

        return openssl_verify($payload, $sigBin, $keyResource, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Verifies the Telnyx Ed25519 webhook signature.
     *
     * Signature is in HTTP_TELNYX_SIGNATURE_ED25519 (base64), timestamp in
     * HTTP_TELNYX_TIMESTAMP. The public key is fetched from
     * service_provider_configuration where service_provider_code = 'telnyx'.
     *
     * Soft-warn when PUBLIC_KEY is not yet configured — same fallback as v1
     * verifyTelnyxSignature() so traffic is not disrupted during key rollout.
     */
    private static function verifyTelnyx(string $rawBody): bool
    {
        $signature = $_SERVER['HTTP_TELNYX_SIGNATURE_ED25519'] ?? '';
        $timestamp  = $_SERVER['HTTP_TELNYX_TIMESTAMP'] ?? '';

        $config        = db()->where('service_provider_code', 'telnyx')->getOne('service_provider_configuration', 'config_details');
        $configDetails = ($config && !empty($config['config_details']))
            ? json_decode($config['config_details'], true)
            : null;
        $publicKey = $configDetails['PUBLIC_KEY'] ?? null;

        if (empty($publicKey)) {
            Logger::warning('Telnyx PUBLIC_KEY not configured — webhook signature check skipped', []);
            return true;
        }

        if ($signature === '' || $timestamp === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedData  = $timestamp . '|' . $rawBody;
        $decodedSig  = base64_decode($signature, true);
        $decodedKey  = base64_decode($publicKey, true);

        if ($decodedSig === false || $decodedKey === false) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($decodedSig, $signedData, $decodedKey);
    }

    private static function verifyCustom(string $vendor, string $rawBody): bool
    {
        $envKey    = 'WEBHOOK_SECRET_' . strtoupper($vendor);
        $secret    = config($envKey) ?: ($_SERVER[$envKey] ?? '');
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }
}
