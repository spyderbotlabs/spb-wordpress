<?php
/**
 * wp-admin settings page.
 *
 * @package SpyderBot_Tracking
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings registration, sanitization, and Test connection.
 */
final class SpyderBot_Tracking_Settings
{
    public const OPTION_GROUP = 'spyderbot_tracking';
    public const OPTION_KEY = 'spyderbot_tracking_key';
    public const OPTION_ENV = 'spyderbot_tracking_environment';
    public const OPTION_CLIENT = 'spyderbot_tracking_client_enabled';
    public const OPTION_SERVER = 'spyderbot_tracking_server_enabled';
    public const OPTION_EXCLUDED = 'spyderbot_tracking_excluded_paths';
    public const OPTION_CACHE_BOT_BYPASS = 'spyderbot_tracking_cache_bot_bypass';

    public const MASK_SENTINEL = '__spyderbot_keep__';

    /**
     * Hook admin UI and settings API.
     */
    public static function register(): void
    {
        add_action('admin_menu', array(self::class, 'add_menu'));
        add_action('admin_init', array(self::class, 'register_settings'));
        add_action('admin_post_spyderbot_test_connection', array(self::class, 'handle_test_connection'));
        add_action('admin_notices', array(self::class, 'maybe_cache_notice'));
    }

    /**
     * Add Settings → SpyderBot Tracking.
     */
    public static function add_menu(): void
    {
        add_options_page(
            __('SpyderBot Tracking', 'spyderbot-tracking'),
            __('SpyderBot Tracking', 'spyderbot-tracking'),
            'manage_options',
            'spyderbot-tracking',
            array(self::class, 'render_page')
        );
    }

    /**
     * Register options with sanitizers.
     */
    public static function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_KEY,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_tracking_key'),
                'default'           => '',
                'show_in_rest'      => false,
            )
        );
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_ENV,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(SpyderBot_Tracking_Environments::class, 'sanitize'),
                'default'           => 'production',
            )
        );
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_CLIENT,
            array(
                'type'              => 'boolean',
                'sanitize_callback' => array(self::class, 'sanitize_checkbox'),
                'default'           => true,
            )
        );
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_SERVER,
            array(
                'type'              => 'boolean',
                'sanitize_callback' => array(self::class, 'sanitize_checkbox'),
                'default'           => true,
            )
        );
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_EXCLUDED,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_excluded_paths'),
                'default'           => '',
            )
        );
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_CACHE_BOT_BYPASS,
            array(
                'type'              => 'boolean',
                'sanitize_callback' => array(self::class, 'sanitize_checkbox'),
                'default'           => true,
            )
        );
    }

    /**
     * Keep existing key when the masked sentinel is posted or field is blank intentionally.
     *
     * @param mixed $value Raw submitted value.
     */
    public static function sanitize_tracking_key($value): string
    {
        $value = is_string($value) ? trim(wp_unslash($value)) : '';

        if ($value === '' || $value === self::MASK_SENTINEL) {
            return (string) get_option(self::OPTION_KEY, '');
        }

        // Strip whitespace / control chars; keys are opaque tokens.
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        return sanitize_text_field((string) $value);
    }

    /**
     * Checkbox: absent POST → false when options.php saves the group.
     *
     * @param mixed $value Raw value.
     */
    public static function sanitize_checkbox($value): bool
    {
        return (bool) $value;
    }

    /**
     * Normalize excluded paths (one per line → leading slash, no query).
     *
     * @param mixed $value Raw textarea.
     */
    public static function sanitize_excluded_paths($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $lines  = preg_split('/\r\n|\r|\n/', wp_unslash($value));
        $clean  = array();
        if (! is_array($lines)) {
            return '';
        }

        foreach ($lines as $line) {
            $line = trim(sanitize_text_field($line));
            if ($line === '') {
                continue;
            }
            if ($line[0] !== '/') {
                $line = '/' . $line;
            }
            // Drop query/hash.
            $path = (string) (parse_url($line, PHP_URL_PATH) ?: $line);
            if ($path !== '' && $path !== '/') {
                $clean[] = $path;
            }
        }

        $clean = array_values(array_unique($clean));
        return implode("\n", $clean);
    }

    public static function get_tracking_key(): string
    {
        return (string) get_option(self::OPTION_KEY, '');
    }

    /**
     * Masked display for the settings input (never echo the full key in HTML value).
     */
    public static function masked_key_display(): string
    {
        $key = self::get_tracking_key();
        if ($key === '') {
            return '';
        }
        $len = strlen($key);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }
        return str_repeat('•', max(8, $len - 4)) . substr($key, -4);
    }

    public static function get_environment(): string
    {
        return SpyderBot_Tracking_Environments::sanitize((string) get_option(self::OPTION_ENV, 'production'));
    }

    /**
     * @return array{label:string, server_track_url:string, script_origin:string}
     */
    public static function get_environment_config(): array
    {
        return SpyderBot_Tracking_Environments::get(self::get_environment());
    }

    public static function get_server_track_url(): string
    {
        $cfg = self::get_environment_config();
        return $cfg['server_track_url'];
    }

    public static function get_script_origin(): string
    {
        $cfg = self::get_environment_config();
        return rtrim($cfg['script_origin'], '/');
    }

    public static function is_client_enabled(): bool
    {
        return (bool) get_option(self::OPTION_CLIENT, true);
    }

    public static function is_server_enabled(): bool
    {
        return (bool) get_option(self::OPTION_SERVER, true);
    }

    public static function is_cache_bot_bypass_enabled(): bool
    {
        return (bool) get_option(self::OPTION_CACHE_BOT_BYPASS, true);
    }

    /**
     * @return list<string>
     */
    public static function get_excluded_paths(): array
    {
        $raw = (string) get_option(self::OPTION_EXCLUDED, '');
        if ($raw === '') {
            return array();
        }
        $parts = preg_split('/\r\n|\r|\n/', $raw);
        return is_array($parts) ? array_values(array_filter(array_map('strval', $parts))) : array();
    }

    /**
     * Admin notice when a full-page cache plugin is active.
     */
    public static function maybe_cache_notice(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || $screen->id !== 'settings_page_spyderbot-tracking') {
            return;
        }

        $plugins = SpyderBot_Tracking_Cache_Compat::detect_cache_plugins();
        if ($plugins === array()) {
            return;
        }

        $names = implode(', ', $plugins);
        echo '<div class="notice notice-warning"><p>';
        echo esc_html(
            sprintf(
                /* translators: %s: comma-separated cache plugin names */
                __('Page cache detected (%s). Cached HTML never runs PHP, so AI bots can be missed by server-side tracking.', 'spyderbot-tracking'),
                $names
            )
        );
        echo ' ';
        esc_html_e(
            'Enable “Exclude AI bot UAs from page cache” below (filters for WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache), or use the SpyderBot Cloudflare Worker if this site is on Cloudflare.',
            'spyderbot-tracking'
        );
        echo '</p></div>';
    }

    /**
     * Render the settings page.
     */
    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $test_result = get_transient('spyderbot_tracking_test_result');
        delete_transient('spyderbot_tracking_test_result');
        $has_key     = self::get_tracking_key() !== '';
        $excluded    = implode("\n", self::get_excluded_paths());
        $env         = self::get_environment();
        $presets     = SpyderBot_Tracking_Environments::presets();
        $cache_plugins = SpyderBot_Tracking_Cache_Compat::detect_cache_plugins();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SpyderBot Tracking', 'spyderbot-tracking'); ?></h1>
            <p>
                <?php
                esc_html_e(
                    'Connect this WordPress site to SpyderBot AI Traffic. Use the Tracking key from SpyderBot → AI Traffic → your project (project ingest key), not a legacy website key.',
                    'spyderbot-tracking'
                );
                ?>
            </p>

            <?php if (is_array($test_result)) : ?>
                <div class="notice notice-<?php echo ! empty($test_result['success']) ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html((string) $test_result['message']); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="spyderbot_tracking_key"><?php esc_html_e('Tracking key', 'spyderbot-tracking'); ?></label>
                        </th>
                        <td>
                            <input
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>"
                                type="password"
                                id="spyderbot_tracking_key"
                                value="<?php echo $has_key ? esc_attr(self::MASK_SENTINEL) : ''; ?>"
                                class="regular-text"
                                autocomplete="new-password"
                                placeholder="<?php echo $has_key ? esc_attr(self::masked_key_display()) : ''; ?>"
                            />
                            <p class="description">
                                <?php
                                if ($has_key) {
                                    esc_html_e('Key is saved. Leave as-is to keep it, or paste a new key to replace it.', 'spyderbot-tracking');
                                    echo ' <code>' . esc_html(self::masked_key_display()) . '</code>';
                                } else {
                                    esc_html_e('Paste the AI Traffic project ingest key.', 'spyderbot-tracking');
                                }
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="spyderbot_tracking_environment"><?php esc_html_e('API environment', 'spyderbot-tracking'); ?></label>
                        </th>
                        <td>
                            <select name="<?php echo esc_attr(self::OPTION_ENV); ?>" id="spyderbot_tracking_environment">
                                <?php foreach ($presets as $id => $preset) : ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($env, $id); ?>>
                                        <?php echo esc_html($preset['label']); ?>
                                        (<?php echo esc_html($preset['server_track_url']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: 1: script origin URL */
                                    esc_html__('Browser script loads from %s/wfm.js for the selected environment.', 'spyderbot-tracking'),
                                    esc_html(self::get_script_origin())
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Browser script (wfm.js)', 'spyderbot-tracking'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_CLIENT); ?>" value="0" />
                            <label>
                                <input name="<?php echo esc_attr(self::OPTION_CLIENT); ?>" type="checkbox" value="1" <?php checked(self::is_client_enabled()); ?> />
                                <?php esc_html_e('Enqueue wfm.js once on front-end pages (humans / engagement)', 'spyderbot-tracking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Server-side bot tracking', 'spyderbot-tracking'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_SERVER); ?>" value="0" />
                            <label>
                                <input name="<?php echo esc_attr(self::OPTION_SERVER); ?>" type="checkbox" value="1" <?php checked(self::is_server_enabled()); ?> />
                                <?php esc_html_e('POST bot/crawler page views (GPTBot, ClaudeBot, etc.) — humans stay on wfm.js', 'spyderbot-tracking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="spyderbot_tracking_excluded_paths"><?php esc_html_e('Excluded paths', 'spyderbot-tracking'); ?></label>
                        </th>
                        <td>
                            <textarea
                                name="<?php echo esc_attr(self::OPTION_EXCLUDED); ?>"
                                id="spyderbot_tracking_excluded_paths"
                                class="large-text code"
                                rows="4"
                                placeholder="/thank-you&#10;/checkout"
                            ><?php echo esc_textarea($excluded); ?></textarea>
                            <p class="description"><?php esc_html_e('One path prefix per line (e.g. /cart). Matched against the request path; skipped for server and browser tracking.', 'spyderbot-tracking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Page cache / bots', 'spyderbot-tracking'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_CACHE_BOT_BYPASS); ?>" value="0" />
                            <label>
                                <input name="<?php echo esc_attr(self::OPTION_CACHE_BOT_BYPASS); ?>" type="checkbox" value="1" <?php checked(self::is_cache_bot_bypass_enabled()); ?> />
                                <?php esc_html_e('Exclude known AI bot User-Agents from page cache (WP Rocket, LiteSpeed Cache, W3TC, WP Super Cache filters)', 'spyderbot-tracking'); ?>
                            </label>
                            <?php if ($cache_plugins !== array()) : ?>
                                <p class="description">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: plugin names */
                                            __('Detected: %s', 'spyderbot-tracking'),
                                            implode(', ', $cache_plugins)
                                        )
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Coverage: UA reject-list filters when PHP still boots for the request. Fully static edge caches still need the Cloudflare Worker. See README “Page cache”.',
                                    'spyderbot-tracking'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr />
            <h2><?php esc_html_e('Test connection', 'spyderbot-tracking'); ?></h2>
            <p><?php esc_html_e('Sends a blocking test POST to the selected server-track URL with your tracking key.', 'spyderbot-tracking'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('spyderbot_test_connection'); ?>
                <input type="hidden" name="action" value="spyderbot_test_connection" />
                <?php submit_button(__('Test connection', 'spyderbot-tracking'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    /**
     * admin-post handler for Test connection.
     */
    public static function handle_test_connection(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'spyderbot-tracking'), 403);
        }

        check_admin_referer('spyderbot_test_connection');

        $key = self::get_tracking_key();
        if ($key === '') {
            set_transient(
                'spyderbot_tracking_test_result',
                array(
                    'success' => false,
                    'message' => __('Save a tracking key first.', 'spyderbot-tracking'),
                ),
                60
            );
            wp_safe_redirect(admin_url('options-general.php?page=spyderbot-tracking'));
            exit;
        }

        $payload = array(
            'url'          => home_url('/'),
            'referrer'     => '',
            'session_id'   => 'wp_test_' . wp_generate_password(12, false, false),
            'client_ip'    => '127.0.0.1',
            'country'      => '',
            'method'       => 'GET',
            'status_code'  => 200,
            'utm_source'   => '',
            'utm_medium'   => '',
            'utm_campaign' => '',
            'source'       => SPYDERBOT_TRACKING_SOURCE,
        );

        $response = SpyderBot_Tracking_Server_Tracker::send_request(
            $payload,
            $key,
            'SpyderBot-WP-Test/' . SPYDERBOT_TRACKING_VERSION,
            true
        );

        if (is_wp_error($response)) {
            $success = false;
            $message = sprintf(
                /* translators: %s: error detail */
                __('Connection failed: %s', 'spyderbot-tracking'),
                $response->get_error_message()
            );
        } else {
            $code    = (int) wp_remote_retrieve_response_code($response);
            $body    = (string) wp_remote_retrieve_body($response);
            $success = $code > 0 && $code < 400;
            if ($success) {
                $message = sprintf(
                    /* translators: %d: HTTP status */
                    __('Connection successful (HTTP %d).', 'spyderbot-tracking'),
                    $code
                );
            } else {
                $snippet = $body !== '' ? wp_strip_all_tags(substr($body, 0, 200)) : __('(empty body)', 'spyderbot-tracking');
                $message = sprintf(
                    /* translators: 1: HTTP status, 2: body snippet */
                    __('Connection failed (HTTP %1$d): %2$s', 'spyderbot-tracking'),
                    $code,
                    $snippet
                );
            }
        }

        set_transient(
            'spyderbot_tracking_test_result',
            array(
                'success' => $success,
                'message' => $message,
            ),
            60
        );

        wp_safe_redirect(admin_url('options-general.php?page=spyderbot-tracking'));
        exit;
    }
}
