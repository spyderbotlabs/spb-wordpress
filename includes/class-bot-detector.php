<?php
/**
 * Lightweight UA-based bot / LLM detector for server-side gating.
 *
 * Aligns with CloudflareWorkerSnippetBuilder BOT_UA_RE (enough to skip humans).
 * The ingest API also classifies; this only avoids posting humans from PHP.
 *
 * @package SpyderBot_Tracking
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Detect crawler / LLM bots from User-Agent.
 */
final class SpyderBot_Tracking_Bot_Detector
{
    /**
     * Patterns matching known AI / search / SEO crawlers (case-insensitive).
     *
     * @var list<string>
     */
    private static $patterns = array(
        'gptbot',
        'chatgpt',
        'claudebot',
        'anthropic',
        'perplexity',
        'google-extended',
        'bingbot',
        'googlebot',
        'bytespider',
        'petalbot',
        'amazonbot',
        'applebot',
        'duckduckbot',
        'yandexbot',
        'baiduspider',
        'semrushbot',
        'ahrefsbot',
        'facebookexternalhit',
        'meta-externalagent',
        'twitterbot',
        'linkedinbot',
        'slurp',
        'ccbot',
        'diffbot',
        'ia_archiver',
        'openai',
        'cohere',
        'deepseek',
        'grok',
        'bot',
        'crawler',
        'spider',
        'scraper',
    );

    /**
     * Whether the User-Agent looks like a bot / crawler / LLM agent.
     */
    public static function is_bot(string $user_agent): bool
    {
        $ua = strtolower(trim($user_agent));
        if ($ua === '') {
            return false;
        }

        foreach (self::$patterns as $pattern) {
            if (false !== strpos($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
