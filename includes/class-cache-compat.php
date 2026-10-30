<?php
/**
 * Page-cache compatibility — exclude AI bot UAs so PHP can still run for bots.
 *
 * @package SpyderBot_Tracking
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Detect popular cache plugins and register UA reject filters.
 */
final class SpyderBot_Tracking_Cache_Compat
{
    /**
     * Known AI / crawler UA substrings to keep out of full-page cache.
     *
     * @return list<string>
     */
    public static function bot_ua_list(): array
    {
        return array(
            'GPTBot',
            'ChatGPT',
            'ClaudeBot',
            'anthropic',
            'PerplexityBot',
            'Google-Extended',
            'Bytespider',
            'Amazonbot',
            'CCBot',
            'Diffbot',
            'meta-externalagent',
            'Applebot',
            'Bingbot',
            'Googlebot',
        );
    }

    /**
     * Register filters when the setting is enabled.
     */
    public static function register(): void
    {
        add_action('plugins_loaded', array(self::class, 'boot_filters'), 20);
    }

    /**
     * Attach cache-plugin filters after other plugins load.
     */
    public static function boot_filters(): void
    {
        if (! SpyderBot_Tracking_Settings::is_cache_bot_bypass_enabled()) {
            return;
        }

        // WP Rocket.
        add_filter('rocket_cache_reject_ua', array(self::class, 'merge_ua_list'));

        // W3 Total Cache.
        add_filter('w3tc_pagecache_reject_ua', array(self::class, 'merge_ua_list'));

        // LiteSpeed Cache — mark bot requests non-cacheable when PHP runs.
        add_filter('litespeed_can_cache', array(self::class, 'litespeed_can_cache'), 10, 1);
        add_filter('litespeed_cache_is_cacheable', array(self::class, 'litespeed_can_cache'), 10, 1);

        // WP Super Cache — rejected UA list.
        add_action('init', array(self::class, 'wp_super_cache_reject_uas'), 1);

        // Generic constant when a bot hits an uncached miss / bypass.
        add_action('template_redirect', array(self::class, 'maybe_donotcachepage'), 0);
    }

    /**
     * @param mixed $list Existing UA list.
     * @return list<string>
     */
    public static function merge_ua_list($list): array
    {
        if (! is_array($list)) {
            $list = array();
        }
        /** @var list<string> $list */
        $merged = array_merge($list, self::bot_ua_list());
        $merged = array_values(array_unique(array_map('strval', $merged)));
        return $merged;
    }

    /**
     * @param mixed $can Whether LiteSpeed may cache.
     * @return mixed
     */
    public static function litespeed_can_cache($can)
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT'])
            : '';
        if (SpyderBot_Tracking_Bot_Detector::is_bot($ua)) {
            return false;
        }
        return $can;
    }

    /**
     * Append bot UAs to WP Super Cache rejected list.
     */
    public static function wp_super_cache_reject_uas(): void
    {
        if (! function_exists('wp_cache_setting') && ! defined('WPCACHEHOME')) {
            return;
        }

        global $cache_rejected_user_agent;
        if (! isset($cache_rejected_user_agent) || ! is_array($cache_rejected_user_agent)) {
            $cache_rejected_user_agent = array();
        }
        $cache_rejected_user_agent = self::merge_ua_list($cache_rejected_user_agent);
    }

    /**
     * Set DONOTCACHEPAGE for bot requests when PHP executes.
     */
    public static function maybe_donotcachepage(): void
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT'])
            : '';
        if (! SpyderBot_Tracking_Bot_Detector::is_bot($ua)) {
            return;
        }
        if (! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }

    /**
     * Human-readable names of active full-page cache plugins.
     *
     * @return list<string>
     */
    public static function detect_cache_plugins(): array
    {
        $found = array();

        if (! function_exists('is_plugin_active')) {
            $path = ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_readable($path)) {
                require_once $path;
            }
        }

        $map = array(
            'wp-rocket/wp-rocket.php'                       => 'WP Rocket',
            'litespeed-cache/litespeed-cache.php'           => 'LiteSpeed Cache',
            'w3-total-cache/w3-total-cache.php'             => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php'                   => 'WP Super Cache',
            'sg-cachepress/sg-cachepress.php'               => 'SiteGround Optimizer',
            'nitropack/main.php'                            => 'NitroPack',
            'cache-enabler/cache-enabler.php'               => 'Cache Enabler',
        );

        foreach ($map as $file => $label) {
            if (function_exists('is_plugin_active') && is_plugin_active($file)) {
                $found[] = $label;
                continue;
            }
            // Fallback constants / classes when is_plugin_active unavailable early.
            if ($file === 'wp-rocket/wp-rocket.php' && defined('WP_ROCKET_VERSION')) {
                $found[] = $label;
            } elseif ($file === 'litespeed-cache/litespeed-cache.php' && defined('LSCWP_V')) {
                $found[] = $label;
            } elseif ($file === 'w3-total-cache/w3-total-cache.php' && defined('W3TC')) {
                $found[] = $label;
            } elseif ($file === 'wp-super-cache/wp-cache.php' && defined('WPCACHEHOME')) {
                $found[] = $label;
            }
        }

        return array_values(array_unique($found));
    }
}
