<?php

declare(strict_types=1);

/**
 * API v2 global helpers: request accessors, JSON responses, i18n, auth closures, config cache, pagination.
 */

require_once __DIR__ . '/module_url.php';

use Reunite\Framework\Cloud;
use Reunite\Validator;
use Reunite\Cache;
use Utils\Auth;
use Utils\Pagination;
use Reunite\Framework\Net\Request;
use Utils\Middleware\AuthMiddleware;
use Utils\Middleware\CronMiddleware;
use Utils\Middleware\PortalScopeMiddleware;
use Utils\Middleware\RateLimitMiddleware;
use Utils\Middleware\ServiceMiddleware;
use Utils\Middleware\WebhookMiddleware;
use Reunite\Email\EmailService;
use Reunite\Sms\SmsService;
use Reunite\Logger;
use Utils\NotificationGuard;
use Utils\PortalAccessLog;
use Utils\PostResponse;
use Utils\Middleware\AccessLogMiddleware;
use Utils\Middleware\PatientAuthMiddleware;

/**
 * Returns the active MysqliDb instance registered on {@see Cloud} for the current API scope.
 *
 * @return \Utils\MysqliDbExtended|object
 */
if (!function_exists('db')) {
    function db(): object
    {
        return Cloud::db();
    }
}

/**
 * Current {@see Request} instance (URL, method, query, parsed body, client metadata).
 */
if (!function_exists('request')) {
    function request(): Request
    {
        return Cloud::request();
    }
}

/**
 * Get request post data
 *
 * @param string|null $key The post key to fetch
 * @param mixed $default Default value if key not found
 * @return mixed
 */
if (!function_exists('request_post')) {
    function request_post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return request()->data->getData();
        }
        return request()->data->getData()[$key] ?? $default;
    }
}

/**
 * Get request query data
 *
 * @param string|null $key The query key to fetch
 * @param mixed $default Default value if key not found
 * @return mixed
 */
if (!function_exists('request_query')) {
    function request_query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return request()->query->getData();
        }
        return request()->query->getData()[$key] ?? $default;
    }
}

/**
 * Get request query data
 *
 * @param string|null $key The query key to fetch
 * @param mixed $default Default value if key not found
 * @return mixed
 */
if (!function_exists('request_get')) {
    function request_get(?string $key = null, $default = null)
    {
        return request_query($key, $default);
    }
}

/**
 * Get request IP address
 *
 * @param string|null $default Default value if IP not found
 * @return string
 */
if (!function_exists('request_ip')) {
    function request_ip(?string $default = null): string
    {
        return request()->ip ?? $default;
    }
}

/**
 * Resolve client IP the same way v1 GeneralClass::getRealIpAddr() does.
 */
if (!function_exists('real_client_ip')) {
    function real_client_ip(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return (string) $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}

/**
 * Get uploaded files
 *
 * @param string|null $key The file input name
 * @return array|null All files or a specific file
 */
if (!function_exists('file_request')) {
    function file_request(?string $key = null): ?array
    {
        if ($key === null) {
            return $_FILES;
        }
        return $_FILES[$key] ?? null;
    }
}

/**
 * Fetch a URL path parameter captured from a {param} or @param route pattern.
 *
 * The framework (FlightPHP-style) passes route params as positional function
 * arguments, NOT via the request object. Controller methods must declare a
 * `string $urlId = ''` parameter to receive the value directly:
 *
 *   // Router: $router->get('/{id}', [FooController::class, 'show']);
 *   public function show(string $urlId = ''): void { $id = (int) $urlId; }
 *
 * This helper remains for forward-compatibility in case a global param store
 * is introduced later, but currently always returns the default.
 *
 * @param string $key     Route parameter name
 * @param mixed  $default Default value when key is absent
 * @return mixed
 */
if (!function_exists('request_param')) {
    function request_param(string $key, $default = null)
    {
        // Route params are passed as function arguments, not stored on request.
        // Use the $urlId function parameter in the controller method instead.
        return $default;
    }
}


/**
 * Send response
 *
 * @param mixed $data The data to send
 * @param int $code The response code
 * @param int $option The JSON encoding options
 * @return void
 */

if (!function_exists('response')) {
    function response($data, ?int $code = null, ?int $option = null): void
    {
        $code = empty($code) ? $data['code'] ?? 200 : $code;
        if (is_array($data) && !isset($data['meta'])) {
            $data['meta'] = ['request_id' => Cloud::get('request_id') ?? ''];
        }

        if (is_array($data)) {
            PortalAccessLog::finish($data);
        }

        // Test hook: under PHPUnit (APP_ENV=testing), exit() would kill the whole test run and
        // code after a halted validate()/response() call would keep executing. Throw instead so
        // TestCase::callController() can catch it — same halt semantics, but catchable.
        if (($_SERVER['APP_ENV'] ?? '') === 'testing') {
            PostResponse::run();
            throw new \Utils\TestResponseException(is_array($data) ? $data : [], (int) $code);
        }

        Cloud::response()->status($code)->write(json_encode($data, $option ?? 0))->send();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        PostResponse::run();
        exit;
    }
}

/**
 * Send an email via {@see EmailService::send()}, aliasing recipients outside prod.
 *
 * Mirrors v1's GeneralClass::sendSendGridMail(): in any non-prod ENVIRONMENT, every
 * recipient address is rewritten to `local+part@reuniterx.org` and the subject is
 * tagged with the environment name, so templates/from_email wiring still get exercised
 * end-to-end without ever emailing a real user. Gating lives centrally in
 * {@see NotificationGuard::resolveEmailRecipients()}. All v2 controllers should call
 * this instead of EmailService::send() directly.
 *
 * @param array       $recipients List of ['email' => string, 'name' => string] recipients
 * @param string      $subject    Email subject line
 * @param string      $body       HTML body (often empty when using template_id in $options)
 * @param array       $options    EmailService::send() options (from_email, template_id, template_data, etc.)
 * @param string|null $provider   Optional explicit provider name (e.g. 'sendgrid'); default provider if null
 * @return mixed
 * @throws \Exception If EmailService::send() fails
 */
if (!function_exists('sendEmail')) {
    function sendEmail(array $recipients, string $subject, string $body = '', array $options = [], ?string $provider = null)
    {
        [$recipients, $subject] = NotificationGuard::resolveEmailRecipients($recipients, $subject);

        if ($provider !== null) {
            return EmailService::setProvider($provider)->send($recipients, $subject, $body, $options);
        }
        return EmailService::send($recipients, $subject, $body, $options);
    }
}

/**
 * Inline a SendGrid legacy (non `d-`) template so the HTML body actually renders.
 *
 * Reunite\Email's SendGridProvider omits the `content` block whenever template_id
 * is set. That is correct for dynamic templates (`d-...`) but empty for v1-era
 * templates, whose `<%body%>` placeholder is filled from the message HTML — the
 * login-verification email shows the branded chrome and `-main_title-` but no OTP.
 *
 * Callers pick the template ID (dev vs prod); this fetches the active version,
 * substitutes `<%body%>` / `<%content%>` plus any `-key-` replacements, and
 * returns HTML suitable for sendEmail() *without* template_id. If the template
 * cannot be loaded, the original $body is returned so the OTP is still visible.
 *
 * @param string               $templateId    SendGrid legacy template UUID
 * @param string               $body          HTML to inject into <%body%>
 * @param array<string,string> $substitutions Placeholder replacements (e.g. '-main_title-' => 'Login Verification')
 * @return string Full HTML when the template loads; otherwise $body
 */
if (!function_exists('renderLegacyEmailTemplate')) {
    function renderLegacyEmailTemplate(string $templateId, string $body, array $substitutions = []): string
    {
        if ($templateId === '' || strpos($templateId, 'd-') === 0) {
            return $body;
        }

        $cacheKey = 'sendgrid.legacy_html.' . $templateId;
        $html = Cache::get($cacheKey);

        if (!is_string($html) || $html === '') {
            try {
                $template = EmailService::getTemplate($templateId);
            } catch (\Throwable $e) {
                Logger::warning('Failed to fetch SendGrid legacy template', [
                    'location' => 'renderLegacyEmailTemplate',
                    'template_id' => $templateId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
                return $body;
            }

            $html = '';
            foreach ($template['versions'] ?? [] as $version) {
                if ((int) ($version['active'] ?? 0) === 1) {
                    $html = (string) ($version['html_content'] ?? '');
                    break;
                }
            }

            if ($html === '') {
                return $body;
            }

            Cache::set($cacheKey, $html, 3600);
        }

        $rendered = str_replace(['<%body%>', '<%content%>'], $body, $html);
        foreach ($substitutions as $placeholder => $value) {
            $rendered = str_replace((string) $placeholder, (string) $value, $rendered);
        }

        return $rendered;
    }
}

/**
 * Current deployment environment from the `ENVIRONMENT` constant (defined in
 * `config.php` from `$_SERVER['ENVIRONMENT']`). Not a `configuration`-table
 * value — do not use `config('ENVIRONMENT')` for runtime env checks.
 *
 * Named `appEnv()`, not `getEnv()` — PHP function names are case-insensitive,
 * so `getEnv()` collides with the built-in `getenv()`. `function_exists('getEnv')`
 * matches the built-in and returns true, so a guarded `getEnv()` declaration never
 * runs; every call site then silently invokes the zero-arg native `getenv()`, which
 * returns an array of all environment variables instead of the `ENVIRONMENT` string.
 */
if (!function_exists('appEnv')) {
    function appEnv(): string
    {
        if (!defined('ENVIRONMENT') || (string) ENVIRONMENT === '') {
            Logger::error('Missing ENVIRONMENT constant');
            return '';
        }

        return (string) ENVIRONMENT;
    }
}

/**
 * Whether the current deployment is production (`appEnv() === 'prod'`).
 */
if (!function_exists('isProd')) {
    function isProd(): bool
    {
        return appEnv() === 'prod';
    }
}

/**
 * Whether an SMS may actually be sent to this phone number: always true in prod;
 * outside prod, only true if the number is in `whitelist_phone_number`.
 *
 * Thin global wrapper around {@see NotificationGuard::allowSms()} — the same
 * pattern as {@see sendSms()}/{@see config()} — so any v2 Controller/Model can
 * call it without a `use Utils\NotificationGuard;` import. Use this for an
 * explicit pre-check (e.g. to roll back a transaction with a specific "not
 * whitelisted" message before attempting a send); {@see sendSms()} already
 * calls it internally, so callers that don't need an early exit can just call
 * sendSms() directly and inspect the `skipped` key in its response instead.
 *
 * @param string $phone Destination phone number, any format
 */
if (!function_exists('isSmsAllowed')) {
    function isSmsAllowed(string $phone): bool
    {
        return NotificationGuard::allowSms($phone);
    }
}

/**
 * Resolves the SMS provider code (twilio|telnyx|podium|...) configured for a given
 * "from" number, via the active `sms_configuration` row for that number. Defaults
 * to 'twilio' when no active row matches (same as v1's
 * GeneralClass::resolveSmsProviderAndFromNumber()).
 *
 * Thin global wrapper around {@see \Models\ServiceProviderConfigurationModel::findProviderCodeByPhoneNumber()}
 * — the same pattern as {@see isSmsAllowed()}/{@see sendSms()} — so any v2
 * Controller/Model can call it without a `use` import. This was independently
 * reimplemented 3 times (`MessageController`, `CloudOnboardingController`,
 * `PioneerPaymentModel`) before being consolidated here — always call this
 * instead of adding a 4th copy of the join.
 *
 * @param string $fromNumber Sending phone number, any format
 */
if (!function_exists('resolveSmsProviderCode')) {
    function resolveSmsProviderCode(string $fromNumber): string
    {
        return (new \Models\ServiceProviderConfigurationModel())->findProviderCodeByPhoneNumber($fromNumber);
    }
}

/**
 * Send an SMS via {@see SmsService}, suppressing the send outside prod unless the
 * destination number is whitelisted.
 *
 * Gating lives centrally in {@see NotificationGuard::allowSms()} (via {@see isSmsAllowed()})
 * — outside prod, only numbers present in `whitelist_phone_number` are actually texted;
 * every other send is silently skipped. All v2 controllers should call this instead of
 * SmsService directly so no non-prod environment can text a real user, regardless of
 * any feature flag a caller checks upstream.
 *
 * @param string      $provider   SmsService::PROVIDER_* constant
 * @param string      $toNumber   Destination phone number
 * @param string      $fromNumber Sending number
 * @param string      $message    SMS body
 * @param string|null $mediaFile  Optional MMS media URL/path
 * @param array       $options    SmsService::send() options
 * @return array `['skipped' => true]` if suppressed outside prod, otherwise the provider response
 */
if (!function_exists('sendSms')) {
    function sendSms(string $provider, string $toNumber, string $fromNumber, string $message, ?string $mediaFile = null, array $options = []): array
    {
        if (!isSmsAllowed($toNumber)) {
            return ['skipped' => true];
        }

        return SmsService::fromLibConfig()->send($provider, $toNumber, $fromNumber, $message, $mediaFile, $options);
    }
}

/**
 * Queue a callable to run after the HTTP response is sent ({@see PostResponse}).
 *
 * Use for outbound I/O that must not delay the JSON response (SMS, email, webhooks).
 * Tasks run from response() after send(); errors are logged and do not change the status code.
 */
if (!function_exists('defer_after_response')) {
    function defer_after_response(callable $task): void
    {
        PostResponse::defer($task);
    }
}

/**
 * Get language
 *
 * @param string $key The language key
 * @param array $replace The replace array
 * @param string $locale The locale
 * @return string
 */
if (!function_exists('__')) {
    function __(string $key, array $replace = [], string $locale = 'en'): string
    {
        static $langs = [];

        if (!isset($langs[$locale])) {
            $file = Cloud::get('lang_path') . "/$locale.php";
            $langs[$locale] = file_exists($file) ? require $file : [];
        }

        $text = $langs[$locale][$key] ?? $key;

        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, $v, $text);
        }

        return $text;
    }
}



/**
 * Validate data
 *
 * @param array $data The data to validate
 * @param array $rules The validation rules
 * @return Validator
 */
if (!function_exists('validator')) {
    function validator(array $data, array $rules): Validator
    {
        return new Validator($data, $rules);
    }
}

/**
 * Returns an AuthMiddleware instance for use as a route middleware argument.
 *
 * @return AuthMiddleware
 */
if (!function_exists('auth')) {
    function auth(): AuthMiddleware
    {
        return new AuthMiddleware();
    }
}

/**
 * Authenticated user ID from Cloud context (populated by Auth::protect() / AuthMiddleware).
 * Returns 0 when auth middleware has not run or the user is absent.
 *
 * API-only helper — lives in api/v2/functions/global.php (loaded by api/v2/bootstrap.php).
 * Do not call from v2 frontend PHP (v2/portals/, v2/shared/) or JavaScript; the browser
 * has no Cloud context — use PortalAuth.getUser() on the client instead.
 */
if (!function_exists('auth_user_id')) {
    function auth_user_id(): int
    {
        return (int) (Cloud::get('user')['id'] ?? 0);
    }
}

/**
 * Resolves DB + {@see Cloud::get('role_portal')} from POST `portal` (scopes.php key) before staff login handlers run.
 *
 * @return callable
 */
if (!function_exists('login_portal')) {
    function login_portal(): callable
    {
        return static function (): void {
            \Utils\ScopeResolver::applyFromLoginKey(request_post('portal'));
        };
    }
}

/**
 * Binds DB scope from a scopes.php login key before public handlers run (router-supplied, not POST body).
 * Use on unauthenticated routes; staff login uses login_portal() which reads POST portal + requires role_portal.
 *
 * @param string $scope scopes.php key (e.g. patient, admin)
 * @return callable
 */
if (!function_exists('set_portal_scope')) {
    function set_portal_scope(string $scope): callable
    {
        return static function () use ($scope): void {
            \Utils\ScopeResolver::applyPortalScope($scope);
        };
    }
}

/**
 * Role
 *
 * @param string $role The role
 * @return callable
 */
if (!function_exists('role')) {
    function role(string $role): callable
    {
        return fn() => Auth::role($role);
    }
}

/**
 * Role, any of — grants access if the user is super_admin, holds any role in
 * $roles, or (when $module given) has a role_module_access grant for $module.
 * Use for routes that ported a v1 endpoint's multi-role-OR-module check instead
 * of a single exact-match role (see {@see Auth::authorizeAnyOf()}).
 *
 * @param list<string> $roles  Role names that grant access on their own
 * @param string|null  $module Optional role_module_access module key checked as a fallback
 * @return callable
 */
if (!function_exists('role_any')) {
    function role_any(array $roles, ?string $module = null): callable
    {
        return fn() => Auth::authorizeAnyOf($roles, $module);
    }
}

/**
 * Returns a WebhookMiddleware instance for the given vendor.
 *
 * @param string $vendor Vendor key: 'authorize_net', 'twilio', 'sendgrid', or a custom key
 * @return WebhookMiddleware
 */
if (!function_exists('webhook')) {
    function webhook(string $vendor): WebhookMiddleware
    {
        return new WebhookMiddleware($vendor);
    }
}

/**
 * Returns a CronMiddleware instance (IP allowlist + shared secret).
 *
 * @return CronMiddleware
 */
if (!function_exists('cron_auth')) {
    function cron_auth(): CronMiddleware
    {
        return new CronMiddleware();
    }
}

/**
 * Returns a ServiceMiddleware instance for the given DB scope.
 *
 * @param string $scope DB scope the API key must be authorized for (default 'default')
 * @return ServiceMiddleware
 */
if (!function_exists('service_auth')) {
    function service_auth(string $scope = 'default'): ServiceMiddleware
    {
        return new ServiceMiddleware($scope);
    }
}

/**
 * Returns a PatientAuthMiddleware instance (patient portal JWT scheme).
 *
 * @return PatientAuthMiddleware
 */
if (!function_exists('patient_auth')) {
    function patient_auth(): PatientAuthMiddleware
    {
        return new PatientAuthMiddleware();
    }
}

/**
 * Patient portal login URL for SMS/email links.
 *
 * V2: {SITE_URL}/v2/patient/login/{platform}
 * V1: {TINY_URL_PATIENT}{platform}/login/login.php  (controlled by CLOUDRX_PATIENT_PORTAL_LOGIN_LINK_VERSION)
 *
 * Only Cloud Rx has a patient portal today, so any other platform returns ''.
 *
 * @return string Empty when URL base is not configured, or platform is not Cloud Rx.
 */
if (!function_exists('patient_portal_login_url')) {
    function patient_portal_login_url(string $platform): string
    {
        if (normalize_key($platform) == 'cloudrx') {
            if (CLOUDRX_PATIENT_PORTAL_LOGIN_LINK_VERSION === 'V2') {
                return patient_cloudrx_v2_login_url();
            } else {
                $tiny = rtrim((string) config('TINY_URL_PATIENT', ''), '/');
                return $tiny === '' ? '' : $tiny . '/cloudrx/login/login.php';
            }
        }
        return '';
    }
}

/**
 * Patient chat URL for SMS/chat notification links.
 *
 * V2: {SITE_URL}/v2/patient/chat?key={messageKey}
 * V1: {TINY_URL_PATIENT}chat/{messageKey}  (controlled by PATIENT_CHAT_LINK_VERSION)
 *
 * @param string $messageKey 10-char hex message_key from the patients table
 * @return string Absolute URL, empty if base URL not configured
 */
if (!function_exists('patient_chat_url')) {
    function patient_chat_url(string $messageKey): string
    {
        if (PATIENT_CHAT_LINK_VERSION === 'V1') {
            $tiny = rtrim((string) config('TINY_URL_PATIENT', ''), '/');
            return $tiny === '' ? '' : $tiny . '/chat/' . $messageKey;
        }
        $base = defined('SITE_URL') ? (string) SITE_URL : (string) config('root_path', '');
        if ($base === '') {
            return '';
        }
        return rtrim($base, '/') . '/v2/patient/chat?key=' . rawurlencode($messageKey);
    }
}

/**
 * Cloud Rx patient app login URL (always the v2 page, ignores CLOUDRX_PATIENT_PORTAL_LOGIN_LINK_VERSION).
 *
 * @return string {SITE_URL}/v2/patient/login/cloudrx, empty if base URL not configured
 */
if (!function_exists('patient_cloudrx_v2_login_url')) {
    function patient_cloudrx_v2_login_url(): string
    {
        $base = defined('SITE_URL') ? (string) SITE_URL : (string) config('root_path', '');
        return $base === '' ? '' : rtrim($base, '/') . '/v2/patient/login/cloudrx';
    }
}

/**
 * Patient onboarding URL for SMS links.
 *
 * V2: {SITE_URL}/v2/patient/onboarding?guid={guid}
 * V1: {TINY_URL_PATIENT}onboarding/{guid}  (controlled by PATIENT_ONBOARDING_LINK_VERSION)
 *
 * Cloud Rx only: when CLOUDRX_PATIENT_PORTAL_LOGIN_LINK_VERSION === 'V2' and $platform is Cloud Rx,
 * the Cloud Rx portal login link (patient_portal_login_url()) is returned instead, because their
 * onboarding form now lives inside the patient app. Other platforms are unaffected.
 *
 * @param string $guid     Patient guid from cld_patients / patients table
 * @param string $platform Patient platform, resolved by the caller (e.g. 'cloud_rx'); '' keeps the guid link
 * @return string Absolute URL, empty if base URL not configured
 */
if (!function_exists('patient_onboarding_url')) {
    function patient_onboarding_url(string $guid, string $platform = ''): string
    {
        if (CLOUDRX_PATIENT_PORTAL_LOGIN_LINK_VERSION === 'V2' && normalize_key($platform) === 'cloudrx') {
            return patient_portal_login_url($platform);
        }

        if (PATIENT_ONBOARDING_LINK_VERSION === 'V1') {
            $tiny = rtrim((string) config('TINY_URL_PATIENT', ''), '/');
            return $tiny === '' ? '' : $tiny . '/onboarding/' . $guid;
        }

        $base = defined('SITE_URL') ? (string) SITE_URL : (string) config('root_path', '');
        return $base === '' ? '' : rtrim($base, '/') . '/v2/patient/onboarding?guid=' . rawurlencode($guid);
    }
}

/**
 * Mandell's patient onboarding URL for SMS links.
 *
 * V2: {SITE_URL}/v2/patient/mdl-onboarding?guid={guid}
 * V1: {TINY_URL_PATIENT}mdl-onboarding/{guid}  (controlled by PATIENT_MDL_ONBOARDING_LINK_VERSION)
 *
 * @param string $guid Patient guid
 * @return string Absolute URL, empty if base URL not configured
 */
if (!function_exists('patient_mdl_onboarding_url')) {
    function patient_mdl_onboarding_url(string $guid): string
    {
        if (PATIENT_MDL_ONBOARDING_LINK_VERSION === 'V1') {
            $tiny = rtrim((string) config('TINY_URL_PATIENT', ''), '/');
            return $tiny === '' ? '' : $tiny . '/mdl-onboarding/' . $guid;
        }
        $base = defined('SITE_URL') ? (string) SITE_URL : (string) config('root_path', '');
        if ($base === '') {
            return '';
        }
        return rtrim($base, '/') . '/v2/patient/mdl-onboarding?guid=' . rawurlencode($guid);
    }
}

/**
 * Patient Pioneer/Remit quote URL for SMS links.
 *
 * V2: {SITE_URL}/v2/patient/p-quote?c={guid}
 * V1: {TINY_URL_PATIENT}p-quote/{guid}  (controlled by PATIENT_PIONEER_QUOTE_LINK_VERSION)
 *
 * @param string $guid Quote guid
 * @return string Absolute URL, empty if base URL not configured
 */
if (!function_exists('patient_pioneer_quote_url')) {
    function patient_pioneer_quote_url(string $guid): string
    {
        if (PATIENT_PIONEER_QUOTE_LINK_VERSION === 'V1') {
            $tiny = rtrim((string) config('TINY_URL_PATIENT', ''), '/');
            return $tiny === '' ? '' : $tiny . '/p-quote/' . $guid;
        }
        $base = defined('SITE_URL') ? (string) SITE_URL : (string) config('root_path', '');
        if ($base === '') {
            return '';
        }
        return rtrim($base, '/') . '/v2/patient/p-quote?c=' . rawurlencode($guid);
    }
}

/**
 * Patient standard quote URL for SMS links.
 *
 * V2: {SITE_URL}/v2/patient/quote?guid={guid}
 * V1: {TINY_URL_PATIENT}quote/{guid}  (controlled by PATIENT_QUOTE_LINK_VERSION)
 *
 * @param string $guid Quote guid
 * @return string Absolute URL, empty if base URL not configured
 */
if (!function_exists('patient_quote_url')) {
    function patient_quote_url(string $guid): string
    {
        if (PATIENT_QUOTE_LINK_VERSION === 'V1') {
            $tiny = rtrim((string) config('TINY_URL_PATIENT', ''), '/');
            return $tiny === '' ? '' : $tiny . '/quote/' . $guid;
        }
        $base = defined('SITE_URL') ? (string) SITE_URL : (string) config('root_path', '');
        if ($base === '') {
            return '';
        }
        return rtrim($base, '/') . '/v2/patient/quote?guid=' . rawurlencode($guid);
    }
}

/**
 * Patient price list URL for SMS links.
 *
 * V2: {SITE_URL}/v2/patient/pricelist?guid={guid}
 * V1: {TINY_URL_PATIENT}pricelist/{guid}  (controlled by PATIENT_PRICELIST_LINK_VERSION)
 *
 * @param string $guid Price list guid
 * @return string Absolute URL, empty if base URL not configured
 */
if (!function_exists('patient_pricelist_url')) {
    function patient_pricelist_url(string $guid): string
    {
        if (PATIENT_PRICELIST_LINK_VERSION === 'V1') {
            $tiny = rtrim((string) config('TINY_URL_PATIENT', ''), '/');
            return $tiny === '' ? '' : $tiny . '/pricelist/' . $guid;
        }
        $base = defined('SITE_URL') ? (string) SITE_URL : (string) config('root_path', '');
        if ($base === '') {
            return '';
        }
        return rtrim($base, '/') . '/v2/patient/pricelist?guid=' . rawurlencode($guid);
    }
}

/**
 * Audit_db access log middleware (v1 PortalClass::beginPortalAccessLog parity).
 */
if (!function_exists('access_log')) {
    function access_log(string $methodName, bool $logResponse = false, ?string $portal = null): AccessLogMiddleware
    {
        return new AccessLogMiddleware($methodName, $logResponse, $portal);
    }
}

/**
 * Returns a PatientPortalAuthMiddleware instance (v1 phone+DOB+OTP JWT scheme).
 *
 * Distinct from auth()/utils\Auth — the patient portal keeps its own JWT
 * issued by patient/Auth/AuthController instead of the bearer+User-Data
 * scheme every other v2 portal uses. See
 * utils\middleware\PatientPortalAuthMiddleware for the rationale.
 *
 * @return \utils\middleware\PatientPortalAuthMiddleware
 */
if (!function_exists('patient_portal_auth')) {
    function patient_portal_auth(): \utils\middleware\PatientPortalAuthMiddleware
    {
        return new \utils\middleware\PatientPortalAuthMiddleware();
    }
}

/**
 * Returns a PortalScopeMiddleware instance asserting the given scope.
 *
 * @param string $scope Expected api_scope value (e.g. 'admin', 'pharmacy')
 * @return PortalScopeMiddleware
 */
if (!function_exists('portal_scope')) {
    function portal_scope(string $scope): PortalScopeMiddleware
    {
        return new PortalScopeMiddleware($scope);
    }
}

/**
 * Returns a RateLimitMiddleware instance for APCu-based throttling.
 *
 * @param int $max    Maximum requests allowed within the window
 * @param int $window Window duration in seconds
 * @return RateLimitMiddleware
 */
if (!function_exists('rate_limit')) {
    function rate_limit(int $max, int $window): RateLimitMiddleware
    {
        return new RateLimitMiddleware($max, $window);
    }
}

/**
 * Get config
 *
 * Logs an error when the key isn't found in cache, whether or not a `$default`
 * was given — misconfigured `configuration`-table values (missing API keys,
 * vendor URLs, etc.) should be visible in logs rather than silently falling
 * back everywhere they're read. The `$default` value itself is never logged
 * (only the key) since a caller could pass a secret as the fallback.
 *
 * @param string $key The config key
 * @param mixed $default Value returned when the key isn't found
 * @return mixed
 */
if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        $value = Cache::get($key);
        if ($value === null) {
            if ($default !== null) {
                Logger::error('Missing configuration value: ' . $key . ', using default', ['key' => $key]);
                return $default;
            }
            Logger::error('Missing configuration value: ' . $key, ['key' => $key]);
        }
        return $value;
    }
}

/**
 * Set config
 *
 * @param string $key The config key
 * @param mixed $value The config value
 * @param int $ttl The config TTL
 * @return void
 */
if (!function_exists('set_config')) {
    function set_config(string $key, $value, int $ttl = 0): void
    {
        Cache::set($key, $value, $ttl);
    }
}

/**
 * Builds a reunite/* library provider's config array (e.g. api/v2/config/lib_config.php's
 * `sms.twilio`/`sms.telnyx` entries) from `service_provider_configuration.config_details` —
 * NOT from the `configuration` table / config(). That table holds per-app settings;
 * provider credentials (Twilio account_sid/auth_token, Telnyx api_key, ...) live in this
 * separate JSON column instead, matching how v1's provider classes read them.
 *
 * @param string               $providerCode Matches `service_provider_configuration.service_provider_code` (e.g. 'twilio', 'telnyx')
 * @param array<string,string> $keyMap       Target config key => source key in config_details JSON (e.g. ['account_sid' => 'TWILIO_ACCOUNT_SID'])
 * @param array<string,mixed>  $defaults     Static fallback values merged in first (e.g. a placeholder from_number) — overridden by any DB value present
 * @return array<string,mixed>
 */
if (!function_exists('sms_provider_config')) {
    function sms_provider_config(string $providerCode, array $keyMap, array $defaults = []): array
    {
        $details = (new \Models\ServiceProviderConfigurationModel())->findConfigDetails($providerCode);

        $config = $defaults;
        foreach ($keyMap as $targetKey => $sourceKey) {
            if (!empty($details[$sourceKey])) {
                $config[$targetKey] = $details[$sourceKey];
            }
        }

        return $config;
    }
}

/**
 * Server-side pagination using the legacy {@see Pagination} helper and query parameters.
 *
 * @param string       $table   SQL table name
 * @param string|array $fields  Column list
 * @param array        $options Optional filters passed to {@see Pagination::get}
 *
 * @return array<string, mixed>
 */
if (!function_exists('paginate')) {
    function paginate(string $table, $fields = '*', array $options = []): array
    {
        $params = request()->query->getData();
        $pagination = new Pagination(db(), $params);
        return $pagination->get($table, $fields, $options);
    }
}

/**
 * Whether a DataTable instance should use server-side pagination.
 *
 * Reads api/v2/config/datatables.php, keyed by '{portal}.{module}.{table_id}'.
 * Unknown keys default to false (client-side, DataTables paginates in the browser).
 *
 * @param string $key DataTable config key, e.g. 'admin.users.active'
 * @return bool
 */
if (!function_exists('datatable_server_side')) {
    function datatable_server_side(string $key): bool
    {
        static $config = null;
        if ($config === null) {
            $config = require dirname(__DIR__) . '/config/datatables.php';
        }
        return $config[$key] ?? false;
    }
}

/**
 * Presigned S3 URL that forces the browser to display (not download) the object.
 *
 * Overrides object metadata via ResponseContentType / ResponseContentDisposition
 * so PDFs/images render in <iframe>/<img> even when the stored object has a
 * missing or attachment Content-Disposition.
 *
 * Falls back to StorageService::getUrl() if AWS credentials are unavailable.
 *
 * @param string $remotePath        S3 key, e.g. enroll/42/documents/doc.pdf
 * @param string $filename          Suggested filename for the Content-Disposition header
 * @param int    $expiresInSeconds  URL TTL (default 20 minutes)
 */
if (!function_exists('storage_inline_url')) {
    function storage_inline_url(string $remotePath, string $filename = '', int $expiresInSeconds = 1200): string
    {
        $filename = $filename !== '' ? $filename : basename($remotePath);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];
        $contentType = $mimeMap[$ext] ?? 'application/octet-stream';
        $safeName = preg_replace('/["\r\n\\\\]/', '', $filename) ?: 'file';

        try {
            $region = trim((string) (config('S3_REGION') ?? ''));
            $key    = trim((string) (config('S3_ACCESS_KEY') ?? ''));
            $secret = trim((string) (config('S3_SECRET_KEY') ?? ''));
            $bucket = trim((string) (config('S3_BUCKET') ?? ''));
            if ($region === '' || $key === '' || $secret === '' || $bucket === '') {
                throw new \RuntimeException('S3 credentials incomplete');
            }

            $client = new \Aws\S3\S3Client([
                'version'     => 'latest',
                'region'      => $region,
                'credentials' => ['key' => $key, 'secret' => $secret],
            ]);

            $cmd = $client->getCommand('GetObject', [
                'Bucket'                     => $bucket,
                'Key'                        => $remotePath,
                'ResponseContentType'        => $contentType,
                'ResponseContentDisposition' => 'inline; filename="' . $safeName . '"',
            ]);
            $request = $client->createPresignedRequest($cmd, '+' . $expiresInSeconds . ' seconds');

            return (string) $request->getUri();
        } catch (\Throwable $e) {
            Logger::warning($e->getMessage(), [
                'location' => 'storage_inline_url',
                'path'     => $remotePath,
            ]);

            return \Reunite\Storage\StorageService::getInstance()->getUrl($remotePath, $expiresInSeconds);
        }
    }
}
