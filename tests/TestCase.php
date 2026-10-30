<?php
/**
 * Base test case with Brain Monkey + WP stubs.
 *
 * @package SpyderBot_Tracking
 */

declare(strict_types=1);

namespace SpyderBot\Tracking\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /** @var array<string, mixed> */
    protected $options = array();

    /** @var list<array{url:string,args:array}> */
    protected $remotePosts = array();

    /** @var list<array{hook:string,callback:callable,priority:int}> */
    protected $actions = array();

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->options     = array();
        $this->remotePosts = array();
        $this->actions     = array();

        $_SERVER = array(
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST'      => 'example.com',
            'REQUEST_URI'    => '/',
            'REMOTE_ADDR'    => '203.0.113.10',
        );
        $_GET    = array();
        $_COOKIE = array();

        Functions\when('__')->returnArg(1);
        Functions\when('esc_html__')->returnArg(1);
        Functions\when('esc_html_e')->justReturn(null);
        Functions\when('esc_attr')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
        Functions\when('esc_url_raw')->returnArg(1);
        Functions\when('esc_textarea')->returnArg(1);
        Functions\when('sanitize_text_field')->alias(
            static function ($v) {
                return is_string($v) ? trim(strip_tags($v)) : '';
            }
        );
        Functions\when('wp_unslash')->alias(
            static function ($v) {
                return is_string($v) ? stripslashes($v) : $v;
            }
        );
        Functions\when('wp_json_encode')->alias(
            static function ($data) {
                return json_encode($data);
            }
        );
        Functions\when('wp_generate_password')->alias(
            static function ($len = 12) {
                return substr(bin2hex(random_bytes((int) ceil($len / 2))), 0, (int) $len);
            }
        );
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\when('is_ssl')->justReturn(true);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('wp_doing_cron')->justReturn(false);
        Functions\when('is_feed')->justReturn(false);
        Functions\when('is_robots')->justReturn(false);
        Functions\when('is_favicon')->justReturn(false);
        Functions\when('is_404')->justReturn(false);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('is_plugin_active')->justReturn(false);

        $options = &$this->options;
        Functions\when('get_option')->alias(
            static function ($key, $default = false) use (&$options) {
                return array_key_exists($key, $options) ? $options[ $key ] : $default;
            }
        );
        Functions\when('update_option')->alias(
            static function ($key, $value) use (&$options) {
                $options[ $key ] = $value;
                return true;
            }
        );

        $posts = &$this->remotePosts;
        Functions\when('wp_remote_post')->alias(
            static function ($url, $args = array()) use (&$posts) {
                $posts[] = array(
                    'url'  => $url,
                    'args' => $args,
                );
                return array(
                    'response' => array( 'code' => 200 ),
                    'body'     => '{"success":true}',
                );
            }
        );
        Functions\when('wp_remote_retrieve_response_code')->alias(
            static function ($response) {
                if (is_array($response) && isset($response['response']['code'])) {
                    return (int) $response['response']['code'];
                }
                return 0;
            }
        );
        Functions\when('wp_remote_retrieve_body')->alias(
            static function ($response) {
                return is_array($response) && isset($response['body']) ? (string) $response['body'] : '';
            }
        );
        Functions\when('is_wp_error')->alias(
            static function ($thing) {
                return $thing instanceof \WP_Error;
            }
        );

        $actions = &$this->actions;
        Functions\when('add_action')->alias(
            static function ($hook, $callback, $priority = 10, $accepted = 1) use (&$actions) {
                $actions[] = array(
                    'hook'     => $hook,
                    'callback' => $callback,
                    'priority' => $priority,
                );
                return true;
            }
        );
        Functions\when('add_filter')->justReturn(true);
        Functions\when('register_setting')->justReturn(true);
        Functions\when('add_options_page')->justReturn('settings_page_spyderbot-tracking');
        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('plugins_url')->returnArg(1);
        Functions\when('plugin_dir_path')->alias(
            static function ($file) {
                return rtrim(dirname($file), '/') . '/';
            }
        );
        Functions\when('plugin_basename')->alias(
            static function ($file) {
                return basename(dirname($file)) . '/' . basename($file);
            }
        );
        Functions\when('load_plugin_textdomain')->justReturn(true);
        Functions\when('setcookie')->justReturn(true);
        Functions\when('headers_sent')->justReturn(false);
        Functions\when('wp_strip_all_tags')->alias(
            static function ($s) {
                return strip_tags((string) $s);
            }
        );
    }

    protected function tearDown(): void
    {
        // Reset static scheduled flag via reflection.
        $ref = new \ReflectionClass(\SpyderBot_Tracking_Server_Tracker::class);
        if ($ref->hasProperty('scheduled')) {
            $prop = $ref->getProperty('scheduled');
            $prop->setAccessible(true);
            $prop->setValue(null, false);
        }

        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    protected function enableTracking(string $key = 'test_tracking_key_abc123'): void
    {
        $this->options['spyderbot_tracking_key']              = $key;
        $this->options['spyderbot_tracking_server_enabled']   = true;
        $this->options['spyderbot_tracking_client_enabled']   = true;
        $this->options['spyderbot_tracking_environment']      = 'production';
        $this->options['spyderbot_tracking_excluded_paths']   = '';
        $this->options['spyderbot_tracking_cache_bot_bypass'] = true;
    }

    /**
     * Run shutdown callbacks registered during maybe_track.
     */
    protected function flushShutdown(): void
    {
        foreach ($this->actions as $action) {
            if ($action['hook'] === 'shutdown' && is_callable($action['callback'])) {
                call_user_func($action['callback']);
            }
        }
    }
}
