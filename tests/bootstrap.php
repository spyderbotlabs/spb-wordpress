<?php
/**
 * PHPUnit bootstrap (Brain Monkey — no full WordPress install required).
 *
 * @package SpyderBot_Tracking
 */

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (! is_readable($autoload)) {
    fwrite(STDERR, "Run composer install first.\n");
    exit(1);
}
require $autoload;

if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (! defined('SPYDERBOT_TRACKING_VERSION')) {
    define('SPYDERBOT_TRACKING_VERSION', '0.3.0-test');
}
if (! defined('SPYDERBOT_TRACKING_PLUGIN_FILE')) {
    define('SPYDERBOT_TRACKING_PLUGIN_FILE', dirname(__DIR__) . '/spyderbot-tracking.php');
}
if (! defined('SPYDERBOT_TRACKING_PLUGIN_DIR')) {
    define('SPYDERBOT_TRACKING_PLUGIN_DIR', dirname(__DIR__) . '/');
}
if (! defined('SPYDERBOT_TRACKING_SESSION_COOKIE')) {
    define('SPYDERBOT_TRACKING_SESSION_COOKIE', '_spyd_sid');
}
if (! defined('SPYDERBOT_TRACKING_SESSION_TTL')) {
    define('SPYDERBOT_TRACKING_SESSION_TTL', 1800);
}
if (! defined('SPYDERBOT_TRACKING_SCRIPT_VERSION')) {
    define('SPYDERBOT_TRACKING_SCRIPT_VERSION', '1.1.1');
}
if (! defined('SPYDERBOT_TRACKING_SOURCE')) {
    define('SPYDERBOT_TRACKING_SOURCE', 'wordpress_plugin');
}

// Load plugin classes without executing the bootstrapper side effects.
require_once dirname(__DIR__) . '/includes/class-environments.php';
require_once dirname(__DIR__) . '/includes/class-bot-detector.php';
require_once dirname(__DIR__) . '/includes/class-session.php';
require_once dirname(__DIR__) . '/includes/class-settings.php';
require_once dirname(__DIR__) . '/includes/class-server-tracker.php';
require_once dirname(__DIR__) . '/includes/class-client-tracker.php';
require_once dirname(__DIR__) . '/includes/class-cache-compat.php';
