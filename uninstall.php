<?php
/**
 * Uninstall cleanup for SpyderBot Tracking.
 *
 * @package SpyderBot_Tracking
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = array(
    'spyderbot_tracking_key',
    'spyderbot_tracking_environment',
    'spyderbot_tracking_client_enabled',
    'spyderbot_tracking_server_enabled',
    'spyderbot_tracking_excluded_paths',
    'spyderbot_tracking_cache_bot_bypass',
);

foreach ($options as $option) {
    delete_option($option);
}

delete_transient('spyderbot_tracking_test_result');

// Multisite: remove from each site.
if (is_multisite()) {
    $site_ids = get_sites(array( 'fields' => 'ids', 'number' => 0 ));
    if (is_array($site_ids)) {
        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            foreach ($options as $option) {
                delete_option($option);
            }
            delete_transient('spyderbot_tracking_test_result');
            restore_current_blog();
        }
    }
}
