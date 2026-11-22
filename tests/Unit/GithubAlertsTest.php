<?php

namespace Tests\Unit;

use App\Support\Markdown\GithubAlerts;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class GithubAlertsTest extends TestCase
{
    public function test_converts_github_alert_blockquotes_into_alert_boxes(): void
    {
        $html = GithubAlerts::apply(Str::markdown("> [!WARNING]\n> Urgent **info** here.\n\n> Quote thường"));

        $this->assertStringContainsString('md-alert md-alert--warning', $html);
        $this->assertStringContainsString('<p class="md-alert__title">Warning</p>', $html);
        $this->assertStringContainsString('Urgent <strong>info</strong> here.', $html);
        $this->assertStringNotContainsString('[!WARNING]', $html);
        $this->assertStringContainsString('<blockquote>', $html);
    }

    public function test_leaves_html_without_alerts_untouched(): void
    {
        $this->assertSame('<p>Xin chào</p>', GithubAlerts::apply('<p>Xin chào</p>'));
    }

    public function test_keeps_vietnamese_text_intact(): void
    {
        $this->assertStringContainsString(
            'Mẹo hay: đừng quên lưu bài.',
            GithubAlerts::apply(Str::markdown("> [!TIP]\n> Mẹo hay: đừng quên lưu bài."))
        );
    }
}
