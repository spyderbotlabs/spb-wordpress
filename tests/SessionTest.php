<?php
/**
 * @package SpyderBot_Tracking
 */

declare(strict_types=1);

namespace SpyderBot\Tracking\Tests;

use SpyderBot_Tracking_Session;

final class SessionTest extends TestCase
{
    public function test_rejects_invalid_cookie(): void
    {
        $_COOKIE['_spyd_sid'] = 'bad value!!';
        $this->assertSame('', SpyderBot_Tracking_Session::read_cookie());
    }

    public function test_accepts_valid_cookie(): void
    {
        $_COOKIE['_spyd_sid'] = 'wp_abcdef0123456789';
        $this->assertSame('wp_abcdef0123456789', SpyderBot_Tracking_Session::read_cookie());
    }

    public function test_get_or_create_generates_id(): void
    {
        unset($_COOKIE['_spyd_sid']);
        $sid = SpyderBot_Tracking_Session::get_or_create();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{8,128}$/', $sid);
        $this->assertSame($sid, $_COOKIE['_spyd_sid']);
    }
}
