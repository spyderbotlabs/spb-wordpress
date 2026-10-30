<?php
/**
 * Plugin Name:       SpyderBot Tracking
 * Plugin URI:        https://spyderbot.net/integrations
 * Description:       AI Traffic tracking for WordPress — browser script (wfm.js) and server-side bot capture for SpyderBot.
 * Version:           0.3.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SpyderBot
 * Author URI:        https://spyderbot.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       spyderbot-tracking
 *
 * @package SpyderBot_Tracking
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SPYDERBOT_TRACKING_VERSION', '0.3.2');
define('SPYDERBOT_TRACKING_PLUGIN_FILE', __FILE__);
define('SPYDERBOT_TRACKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPYDERBOT_TRACKING_SESSION_COOKIE', '_spyd_sid');
define('SPYDERBOT_TRACKING_SESSION_TTL', 30 * 60);
define('SPYDERBOT_TRACKING_SCRIPT_VERSION', '1.1.1');
define('SPYDERBOT_TRACKING_SOURCE', 'wordpress_plugin');

require_once SPYDERBOT_TRACKING_PLUGIN_DIR . 'includes/class-environments.php';
require_once SPYDERBOT_TRACKING_PLUGIN_DIR . 'includes/class-bot-detector.php';
require_once SPYDERBOT_TRACKING_PLUGIN_DIR . 'includes/class-session.php';
require_once SPYDERBOT_TRACKING_PLUGIN_DIR . 'includes/class-settings.php';
require_once SPYDERBOT_TRACKING_PLUGIN_DIR . 'includes/class-server-tracker.php';
require_once SPYDERBOT_TRACKING_PLUGIN_DIR . 'includes/class-client-tracker.php';
require_once SPYDERBOT_TRACKING_PLUGIN_DIR . 'includes/class-cache-compat.php';

/**
 * Bootstrap the SpyderBot Tracking plugin.
 */
final class SpyderBot_Tracking_Plugin
{
    /**
     * Register the plugins_loaded hook.
     */
    public static function init(): void
    {
        add_action('plugins_loaded', array(self::class, 'boot'));
    }

    /**
     * Boot subsystems.
     */
    public static function boot(): void
    {
        load_plugin_textdomain(
            'spyderbot-tracking',
            false,
            dirname(plugin_basename(SPYDERBOT_TRACKING_PLUGIN_FILE)) . '/languages'
        );

        SpyderBot_Tracking_Settings::register();
        SpyderBot_Tracking_Client_Tracker::register();
        SpyderBot_Tracking_Server_Tracker::register();
        SpyderBot_Tracking_Cache_Compat::register();
    }
}

SpyderBot_Tracking_Plugin::init();
