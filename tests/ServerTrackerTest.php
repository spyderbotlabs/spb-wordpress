<?php
/**
 * @package SpyderBot_Tracking
 */

declare(strict_types=1);

namespace SpyderBot\Tracking\Tests;

use Brain\Monkey\Functions;
use SpyderBot_Tracking_Server_Tracker;

final class ServerTrackerTest extends TestCase
{
    public function test_bot_request_posts_once_with_contract_fields(): void
    {
        $this->enableTracking();
        $_SERVER['HTTP_USER_AGENT']       = 'Mozilla/5.0 (compatible; GPTBot/1.2)';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.20';
        $_SERVER['HTTP_CF_IPCOUNTRY']     = 'us';
        $_SERVER['REQUEST_URI']           = '/blog/hello?utm_source=x&utm_medium=y&utm_campaign=z';
        $_GET['utm_source']               = 'x';
        $_GET['utm_medium']               = 'y';
        $_GET['utm_campaign']             = 'z';

        SpyderBot_Tracking_Server_Tracker::maybe_track();
        $this->flushShutdown();

        $this->assertCount(1, $this->remotePosts);
        $post = $this->remotePosts[0];
        $this->assertSame('https://api.spyderbot.net/server-track', $post['url']);
        $this->assertFalse($post['args']['blocking']);
        $this->assertSame('test_tracking_key_abc123', $post['args']['headers']['X-Tracking-Key']);
        $this->assertSame('Mozilla/5.0 (compatible; GPTBot/1.2)', $post['args']['headers']['User-Agent']);

        $body = json_decode((string) $post['args']['body'], true);
        $this->assertIsArray($body);
        $this->assertSame('wordpress_plugin', $body['source']);
        $this->assertSame('198.51.100.20', $body['client_ip']);
        $this->assertSame('US', $body['country']);
        $this->assertSame('GET', $body['method']);
        $this->assertSame(200, $body['status_code']);
        $this->assertSame('x', $body['utm_source']);
        $this->assertSame('y', $body['utm_medium']);
        $this->assertSame('z', $body['utm_campaign']);
        $this->assertNotEmpty($body['session_id']);
        $this->assertStringContainsString('example.com', $body['url']);
    }

    public function test_human_request_does_not_post(): void
    {
        $this->enableTracking();
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0';

        SpyderBot_Tracking_Server_Tracker::maybe_track();
        $this->flushShutdown();

        $this->assertCount(0, $this->remotePosts);
    }

    public function test_admin_and_ajax_skipped(): void
    {
        $this->enableTracking();
        $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';

        Functions\when('is_admin')->justReturn(true);
        SpyderBot_Tracking_Server_Tracker::maybe_track();
        $this->flushShutdown();
        $this->assertCount(0, $this->remotePosts);

        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_ajax')->justReturn(true);
        $this->resetScheduled();
        SpyderBot_Tracking_Server_Tracker::maybe_track();
        $this->flushShutdown();
        $this->assertCount(0, $this->remotePosts);

        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('wp_doing_cron')->justReturn(true);
        $this->resetScheduled();
        SpyderBot_Tracking_Server_Tracker::maybe_track();
        $this->flushShutdown();
        $this->assertCount(0, $this->remotePosts);
    }

    public function test_excluded_paths_skip_post(): void
    {
        $this->enableTracking();
        $this->options['spyderbot_tracking_excluded_paths'] = "/cart\n/checkout";
        $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';
        $_SERVER['REQUEST_URI']     = '/cart/item/1';

        SpyderBot_Tracking_Server_Tracker::maybe_track();
        $this->flushShutdown();
        $this->assertCount(0, $this->remotePosts);
    }

    public function test_failure_never_breaks_page(): void
    {
        $this->enableTracking();
        $_SERVER['HTTP_USER_AGENT'] = 'GPTBot/1.0';

        Functions\when('wp_remote_post')->alias(
            static function () {
                throw new \RuntimeException('network down');
            }
        );

        SpyderBot_Tracking_Server_Tracker::maybe_track();
        // Should not throw from shutdown either.
        $this->flushShutdown();
        $this->assertTrue(true);
    }

    public function test_xff_fallback_for_client_ip(): void
    {
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 10.0.0.1';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';
        $this->assertSame('203.0.113.50', SpyderBot_Tracking_Server_Tracker::client_ip());
    }

    public function test_response_status_code_reflects_404(): void
    {
        Functions\when('is_404')->justReturn(true);
        $this->assertSame(404, SpyderBot_Tracking_Server_Tracker::response_status_code());
    }

    public function test_payload_status_code_uses_response_status(): void
    {
        Functions\when('is_404')->justReturn(true);
        $body = SpyderBot_Tracking_Server_Tracker::build_payload('sess_test');
        $this->assertSame(404, $body['status_code']);
    }

    private function resetScheduled(): void
    {
        $ref  = new \ReflectionClass(SpyderBot_Tracking_Server_Tracker::class);
        $prop = $ref->getProperty('scheduled');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
        $this->actions     = array();
        $this->remotePosts = array();
    }
}
