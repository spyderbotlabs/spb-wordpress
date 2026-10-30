<?php
/**
 * @package SpyderBot_Tracking
 */

declare(strict_types=1);

namespace SpyderBot\Tracking\Tests;

use SpyderBot_Tracking_Bot_Detector;

final class BotDetectorTest extends TestCase
{
    public function test_detects_llm_bots(): void
    {
        $this->assertTrue(SpyderBot_Tracking_Bot_Detector::is_bot('Mozilla/5.0 (compatible; GPTBot/1.0)'));
        $this->assertTrue(SpyderBot_Tracking_Bot_Detector::is_bot('ClaudeBot/1.0'));
        $this->assertTrue(SpyderBot_Tracking_Bot_Detector::is_bot('PerplexityBot/1.0'));
    }

    public function test_humans_are_not_bots(): void
    {
        $this->assertFalse(
            SpyderBot_Tracking_Bot_Detector::is_bot(
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36'
            )
        );
        $this->assertFalse(SpyderBot_Tracking_Bot_Detector::is_bot(''));
    }
}
