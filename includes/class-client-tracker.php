<?php
/**
 * Enqueue remote wfm.js (once, deferred / async).
 *
 * @package SpyderBot_Tracking
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Browser script loader shared with `_spyd_sid`.
 */
final class SpyderBot_Tracking_Client_Tracker
{
    public const HANDLE = 'spyderbot-wfm';

    /**
     * Register enqueue hook.
     */
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', array(self::class, 'enqueue'), 20);
        add_filter('script_loader_tag', array(self::class, 'async_script_tag'), 10, 3);
    }

    /**
     * Enqueue wfm.js from the selected environment origin.
     */
    public static function enqueue(): void
    {
        if (! SpyderBot_Tracking_Settings::is_client_enabled()) {
            return;
        }

        $key = SpyderBot_Tracking_Settings::get_tracking_key();
        if ($key === '') {
            return;
        }

        if (SpyderBot_Tracking_Server_Tracker::should_skip()) {
            return;
        }

        // Ensure shared session cookie exists before wfm.js runs.
        SpyderBot_Tracking_Session::get_or_create();

        $origin = SpyderBot_Tracking_Settings::get_script_origin();
        $src    = $origin . '/wfm.js?key=' . rawurlencode($key) . '&v=' . rawurlencode(SPYDERBOT_TRACKING_SCRIPT_VERSION);

        // Fifth arg true = footer. Async/defer via script_loader_tag (WP 6.0+ safe).
        wp_enqueue_script(self::HANDLE, $src, array(), null, true);

        // Preconnect for non-blocking fetch of wfm.js.
        add_action('wp_head', array(self::class, 'print_preconnect'), 1);
    }

    /**
     * Print preconnect to the script origin once.
     */
    public static function print_preconnect(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done   = true;
        $origin = SpyderBot_Tracking_Settings::get_script_origin();
        printf(
            '<link rel="preconnect" href="%s" crossorigin />' . "\n",
            esc_url($origin)
        );
    }

    /**
     * Force async when WP version lacks script strategy support.
     *
     * @param string $tag    Script HTML.
     * @param string $handle Handle.
     * @param string $src    Source URL.
     */
    public static function async_script_tag(string $tag, string $handle, string $src): string
    {
        if ($handle !== self::HANDLE) {
            return $tag;
        }

        if (false !== strpos($tag, ' async') || false !== strpos($tag, ' defer')) {
            return $tag;
        }

        return str_replace(' src=', ' async defer src=', $tag);
    }
}
