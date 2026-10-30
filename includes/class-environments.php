<?php
/**
 * API / script environment presets.
 *
 * @package SpyderBot_Tracking
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Production ingest + browser-script origin.
 */
final class SpyderBot_Tracking_Environments
{
    /**
     * Preset map keyed by environment id.
     *
     * Production uses the canonical public ingest host (api.spyderbot.net, no /api prefix).
     *
     * @return array<string, array{label:string, server_track_url:string, script_origin:string}>
     */
    public static function presets(): array
    {
        return array(
            'production' => array(
                'label'            => __('Production', 'spyderbot-tracking'),
                'server_track_url' => 'https://api.spyderbot.net/server-track',
                'script_origin'    => 'https://spyderbot.net',
            ),
        );
    }

    /**
     * @param string $env Environment id.
     * @return array{label:string, server_track_url:string, script_origin:string}
     */
    public static function get(string $env): array
    {
        $presets = self::presets();
        if (isset($presets[ $env ])) {
            return $presets[ $env ];
        }

        return $presets['production'];
    }

    /**
     * Normalize a stored environment id.
     */
    public static function sanitize(string $env): string
    {
        $env = strtolower(trim($env));
        return isset(self::presets()[ $env ]) ? $env : 'production';
    }
}
