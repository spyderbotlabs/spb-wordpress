<?php
/**
 * Shared `_spyd_sid` session cookie (SameSite=Lax, 30-min rolling).
 *
 * @package SpyderBot_Tracking
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * First-party session id shared with wfm.js.
 */
final class SpyderBot_Tracking_Session
{
    /**
     * Read or create `_spyd_sid` and refresh Max-Age.
     */
    public static function get_or_create(): string
    {
        $existing = self::read_cookie();
        if ($existing !== '') {
            self::write_cookie($existing);
            return $existing;
        }

        $sid = self::generate();
        self::write_cookie($sid);

        return $sid;
    }

    /**
     * @return string Existing cookie value or empty string.
     */
    public static function read_cookie(): string
    {
        if (empty($_COOKIE[ SPYDERBOT_TRACKING_SESSION_COOKIE ])) {
            return '';
        }

        $raw = sanitize_text_field(wp_unslash((string) $_COOKIE[ SPYDERBOT_TRACKING_SESSION_COOKIE ]));
        if (! preg_match('/^[A-Za-z0-9_-]{8,128}$/', $raw)) {
            return '';
        }

        return $raw;
    }

    /**
     * Write / refresh the session cookie when headers allow.
     */
    public static function write_cookie(string $sid): void
    {
        if ($sid === '') {
            return;
        }

        // Always expose to the current request (wfm.js / PHP in same hit).
        $_COOKIE[ SPYDERBOT_TRACKING_SESSION_COOKIE ] = $sid;

        if (headers_sent()) {
            return;
        }

        $secure = is_ssl();
        // PHP 7.4-compatible setcookie signature with SameSite via options array (7.3+).
        setcookie(
            SPYDERBOT_TRACKING_SESSION_COOKIE,
            $sid,
            array(
                'expires'  => time() + SPYDERBOT_TRACKING_SESSION_TTL,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            )
        );
    }

    /**
     * Generate a new session id.
     */
    public static function generate(): string
    {
        try {
            $bytes = random_bytes(16);
            return 'wp_' . bin2hex($bytes);
        } catch (Exception $e) {
            return 'wp_' . wp_generate_password(24, false, false);
        }
    }
}
