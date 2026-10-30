<?php
/**
 * Fire-and-forget server-side bot tracking.
 *
 * @package SpyderBot_Tracking
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Posts bot HTML page views to /server-track.
 */
final class SpyderBot_Tracking_Server_Tracker
{
    /** @var bool */
    private static $scheduled = false;

    /**
     * Register front-end hooks.
     */
    public static function register(): void
    {
        add_action('template_redirect', array(self::class, 'maybe_track'), 1);
    }

    /**
     * Queue a non-blocking POST for bot HTML requests.
     */
    public static function maybe_track(): void
    {
        try {
            if (! SpyderBot_Tracking_Settings::is_server_enabled()) {
                return;
            }

            $key = SpyderBot_Tracking_Settings::get_tracking_key();
            if ($key === '') {
                return;
            }

            if (self::should_skip()) {
                return;
            }

            $user_agent = self::request_user_agent();
            // Humans are owned by wfm.js — do not POST from PHP.
            if (! SpyderBot_Tracking_Bot_Detector::is_bot($user_agent)) {
                // Still refresh shared session cookie for humans when PHP runs.
                SpyderBot_Tracking_Session::get_or_create();
                return;
            }

            $session_id = SpyderBot_Tracking_Session::get_or_create();
            $payload    = self::build_payload($session_id);

            self::schedule_async($payload, $key, $user_agent);
        } catch (Throwable $e) {
            // Never break the page.
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('SpyderBot Tracking: ' . $e->getMessage());
            }
        }
    }

    /**
     * Whether this request should skip server tracking entirely.
     */
    public static function should_skip(): bool
    {
        if (is_admin()) {
            return true;
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return true;
        }
        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return true;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return true;
        }
        if (function_exists('is_feed') && is_feed()) {
            return true;
        }
        if (function_exists('is_robots') && is_robots()) {
            return true;
        }
        if (function_exists('is_favicon') && is_favicon()) {
            return true;
        }

        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper((string) wp_unslash($_SERVER['REQUEST_METHOD']))
            : 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') {
            return true;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if (preg_match('/\.(css|js|mjs|map|png|jpe?g|gif|svg|ico|webp|avif|woff2?|ttf|eot|mp4|webm|mp3|pdf|zip|gz|xml|txt|json)$/i', $uri)) {
            return true;
        }

        if (self::path_is_excluded()) {
            return true;
        }

        return false;
    }

    /**
     * Match request path against excluded path prefixes.
     */
    public static function path_is_excluded(): bool
    {
        $path = self::request_path();
        foreach (SpyderBot_Tracking_Settings::get_excluded_paths() as $prefix) {
            if ($prefix !== '' && strpos($path, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Current request path (no query string).
     */
    public static function request_path(): string
    {
        $uri  = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return '/';
        }
        return $path;
    }

    /**
     * @param string $session_id Shared session id.
     * @return array<string, mixed>
     */
    public static function build_payload(string $session_id): array
    {
        $utm = self::utm_from_request();

        return array(
            'url'          => self::current_url(),
            'referrer'     => isset($_SERVER['HTTP_REFERER'])
                ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_REFERER']))
                : '',
            'session_id'   => $session_id,
            'client_ip'    => self::client_ip(),
            'country'      => self::client_country(),
            'method'       => isset($_SERVER['REQUEST_METHOD'])
                ? strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])))
                : 'GET',
            'status_code'  => self::response_status_code(),
            'utm_source'   => $utm['utm_source'],
            'utm_medium'   => $utm['utm_medium'],
            'utm_campaign' => $utm['utm_campaign'],
            'source'       => SPYDERBOT_TRACKING_SOURCE,
        );
    }

    /**
     * Best-effort HTTP status for the current response (never invent success).
     */
    public static function response_status_code(): int
    {
        if (function_exists('is_404') && is_404()) {
            return 404;
        }

        $code = function_exists('http_response_code') ? http_response_code() : false;
        if (is_int($code) && $code >= 100 && $code <= 599) {
            return $code;
        }

        return 200;
    }

    /**
     * @return array{utm_source:string,utm_medium:string,utm_campaign:string}
     */
    public static function utm_from_request(): array
    {
        $get = function ($key) {
            if (! isset($_GET[ $key ])) {
                return '';
            }
            return sanitize_text_field(wp_unslash((string) $_GET[ $key ]));
        };

        return array(
            'utm_source'   => $get('utm_source'),
            'utm_medium'   => $get('utm_medium'),
            'utm_campaign' => $get('utm_campaign'),
        );
    }

    /**
     * Resolve client IP: CF-Connecting-IP → X-Forwarded-For → REMOTE_ADDR.
     */
    public static function client_ip(): string
    {
        $candidates = array();

        if (! empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = (string) wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $xff = (string) wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']);
            $parts = explode(',', $xff);
            $candidates[] = trim($parts[0]);
        }
        if (! empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = (string) wp_unslash($_SERVER['REMOTE_ADDR']);
        }

        foreach ($candidates as $ip) {
            $ip = sanitize_text_field($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '';
    }

    /**
     * Cloudflare country header when present.
     */
    public static function client_country(): string
    {
        if (empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return '';
        }
        $cc = strtoupper(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_CF_IPCOUNTRY'])));
        if (! preg_match('/^[A-Z]{2}$/', $cc)) {
            return '';
        }
        return $cc;
    }

    public static function current_url(): string
    {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = isset($_SERVER['HTTP_HOST'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST']))
            : '';
        $uri    = isset($_SERVER['REQUEST_URI'])
            ? (string) wp_unslash($_SERVER['REQUEST_URI'])
            : '/';

        return esc_url_raw($scheme . '://' . $host . $uri);
    }

    public static function request_user_agent(): string
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return '';
        }
        return sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']));
    }

    /**
     * Schedule fire-and-forget send on shutdown (after response when possible).
     *
     * @param array<string, mixed> $payload Body.
     * @param string               $key     Tracking key.
     * @param string               $ua      Visitor UA.
     */
    public static function schedule_async(array $payload, string $key, string $ua): void
    {
        if (self::$scheduled) {
            return;
        }
        self::$scheduled = true;

        $send = static function () use ($payload, $key, $ua) {
            if (function_exists('fastcgi_finish_request')) {
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                @fastcgi_finish_request();
            }
            try {
                self::send_request($payload, $key, $ua, false);
            } catch (Throwable $e) {
                // Swallow.
            }
        };

        add_action('shutdown', $send, 0);
    }

    /**
     * POST to server-track.
     *
     * @param array<string, mixed> $payload  JSON body.
     * @param string               $key      X-Tracking-Key.
     * @param string               $user_agent Visitor UA header.
     * @param bool                 $blocking Whether to wait for the response.
     * @return array|\WP_Error
     */
    public static function send_request(array $payload, string $key, string $user_agent = '', bool $blocking = false)
    {
        $headers = array(
            'Content-Type'   => 'application/json',
            'X-Tracking-Key' => $key,
        );

        if ($user_agent !== '') {
            $headers['User-Agent'] = $user_agent;
        }

        $url = SpyderBot_Tracking_Settings::get_server_track_url();

        return wp_remote_post(
            $url,
            array(
                'headers'   => $headers,
                'body'      => wp_json_encode($payload),
                'timeout'   => $blocking ? 1.5 : 1.0,
                'blocking'  => $blocking,
                'sslverify' => true,
                'data_format' => 'body',
            )
        );
    }
}
