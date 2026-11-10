<?php

declare(strict_types=1);

namespace Utils;

use Models\WhitelistPhoneModel;

/**
 * Single central gate deciding whether an email, SMS, or fax may reach a real
 * recipient.
 *
 * Outside `ENVIRONMENT=prod`, email recipients are aliased to a reuniterx.org
 * catch-all, and SMS/fax destinations are suppressed unless explicitly
 * whitelisted (`whitelist_phone_number` table for SMS, `TESTING_FAX_NUMBER`
 * config for fax). {@see sendEmail()} and {@see sendSms()}
 * (api/v2/functions/global.php) and {@see \Services\FaxService} all route
 * through this class, so no non-prod environment can ever notify a real user
 * — regardless of what feature flags a caller checks upstream.
 */
class NotificationGuard
{
    /**
     * Rewrites recipients and tags the subject outside prod so email always
     * lands in a reuniterx.org catch-all instead of a real inbox.
     *
     * @param array<int, array<string, mixed>> $recipients List of ['email' => string, 'name' => string]
     * @param string $subject Email subject line
     * @return array{0: array<int, array<string, mixed>>, 1: string} [aliased recipients, aliased subject]
     */
    public static function resolveEmailRecipients(array $recipients, string $subject): array
    {
        if (isProd()) {
            return [$recipients, $subject];
        }

        foreach ($recipients as &$recipient) {
            $email = (string) ($recipient['email'] ?? '');
            if ($email !== '') {
                $recipient['email'] = str_replace('@', '+', $email) . '@reuniterx.org';
            }
        }
        unset($recipient);

        $environment = appEnv();

        return [$recipients, $subject . ' - ' . ($environment !== '' ? $environment : 'unknown')];
    }

    /**
     * Whether an SMS may be sent to this number: always true in prod,
     * otherwise only if the number is in `whitelist_phone_number`.
     *
     * @param string $phone Destination phone number, any format
     */
    public static function allowSms(string $phone): bool
    {
        if (isProd()) {
            return true;
        }

        $digits = substr(preg_replace('/\D/', '', $phone) ?? '', -10);
        if ($digits === '') {
            return false;
        }

        return (new WhitelistPhoneModel())->isWhitelisted($digits);
    }

    /**
     * Whether a fax may be sent to this number: always true in prod,
     * otherwise only if it matches the configured testing fax number.
     *
     * @param string $faxNumber Destination fax number
     * @param string $testingFaxNumber Value of the `TESTING_FAX_NUMBER` config key
     */
    public static function allowFax(string $faxNumber, string $testingFaxNumber): bool
    {
        if (isProd()) {
            return true;
        }

        $faxDigits = substr(preg_replace('/\D/', '', $faxNumber) ?? '', -10);
        $testingDigits = substr(preg_replace('/\D/', '', $testingFaxNumber) ?? '', -10);

        return $faxDigits !== '' && $testingDigits !== '' && $faxDigits === $testingDigits;
    }
}
