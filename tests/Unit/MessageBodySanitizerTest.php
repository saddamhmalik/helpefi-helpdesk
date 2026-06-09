<?php

namespace Tests\Unit;

use App\Domains\Tickets\Support\MessageBodySanitizer;
use PHPUnit\Framework\TestCase;

class MessageBodySanitizerTest extends TestCase
{
    public function test_sanitize_strips_unsafe_tags_and_attributes(): void
    {
        $body = '<p>Hello</p><script>alert(1)</script><p onclick="evil">World</p>';

        $this->assertSame('<p>Hello</p><p>World</p>', MessageBodySanitizer::sanitize($body));
    }

    public function test_to_plain_text_converts_html_to_text(): void
    {
        $body = '<p>Hello<br>World</p><ul><li>One</li></ul>';

        $this->assertStringContainsString('Hello', MessageBodySanitizer::toPlainText($body));
        $this->assertStringContainsString('One', MessageBodySanitizer::toPlainText($body));
    }

    public function test_is_empty_detects_blank_editor_content(): void
    {
        $this->assertTrue(MessageBodySanitizer::isEmpty('<p></p>'));
        $this->assertTrue(MessageBodySanitizer::isEmpty('   '));
        $this->assertFalse(MessageBodySanitizer::isEmpty('<p>Reply</p>'));
    }
}
