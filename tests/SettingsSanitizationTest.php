<?php
/**
 * @package SpyderBot_Tracking
 */

declare(strict_types=1);

namespace SpyderBot\Tracking\Tests;

use SpyderBot_Tracking_Environments;
use SpyderBot_Tracking_Settings;

final class SettingsSanitizationTest extends TestCase
{
    public function test_sanitize_tracking_key_keeps_existing_on_sentinel(): void
    {
        $this->options['spyderbot_tracking_key'] = 'existing_secret_key';
        $this->assertSame(
            'existing_secret_key',
            SpyderBot_Tracking_Settings::sanitize_tracking_key(SpyderBot_Tracking_Settings::MASK_SENTINEL)
        );
        $this->assertSame(
            'existing_secret_key',
            SpyderBot_Tracking_Settings::sanitize_tracking_key('')
        );
    }

    public function test_sanitize_tracking_key_accepts_new_value(): void
    {
        $this->options['spyderbot_tracking_key'] = 'old';
        $this->assertSame(
            'brand_new_key_99',
            SpyderBot_Tracking_Settings::sanitize_tracking_key(' brand_new_key_99 ')
        );
    }

    public function test_sanitize_excluded_paths(): void
    {
        $raw = "cart\n/checkout?x=1\n\n/ok\n/";
        $out = SpyderBot_Tracking_Settings::sanitize_excluded_paths($raw);
        $this->assertSame("/cart\n/checkout\n/ok", $out);
    }

    public function test_sanitize_checkbox(): void
    {
        $this->assertFalse(SpyderBot_Tracking_Settings::sanitize_checkbox(0));
        $this->assertFalse(SpyderBot_Tracking_Settings::sanitize_checkbox(''));
        $this->assertTrue(SpyderBot_Tracking_Settings::sanitize_checkbox(1));
        $this->assertTrue(SpyderBot_Tracking_Settings::sanitize_checkbox('1'));
    }

    public function test_environment_sanitize(): void
    {
        // Staging is not a public preset — unknown values fall back to production.
        $this->assertSame('production', SpyderBot_Tracking_Environments::sanitize('staging'));
        $this->assertSame('production', SpyderBot_Tracking_Environments::sanitize('nope'));
        $this->assertSame('production', SpyderBot_Tracking_Environments::sanitize('production'));
        $prod = SpyderBot_Tracking_Environments::get('staging');
        $this->assertSame('https://api.spyderbot.net/server-track', $prod['server_track_url']);
        $this->assertArrayNotHasKey('staging', SpyderBot_Tracking_Environments::presets());
    }

    public function test_masked_key_display_hides_secret(): void
    {
        $this->options['spyderbot_tracking_key'] = 'supersecretkeyvalue';
        $masked = SpyderBot_Tracking_Settings::masked_key_display();
        $this->assertStringNotContainsString('supersecret', $masked);
        $this->assertStringEndsWith('alue', $masked);
    }
}
