<?php

namespace Tests\Unit\Channels;

use App\Domains\Channels\Support\ThankYouMessageDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThankYouMessageDetectorTest extends TestCase
{
    private ThankYouMessageDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new ThankYouMessageDetector;
    }

    #[DataProvider('thankYouMessages')]
    public function test_detects_thank_you_messages(string $body): void
    {
        $this->assertTrue($this->detector->isThankYouNote($body));
    }

    public static function thankYouMessages(): array
    {
        return [
            ['Thanks!'],
            ['Thank you so much for your help'],
            ['Many thanks'],
            ['Much appreciated'],
            ['Thx'],
            ['Cheers'],
            ['That worked, thanks'],
            ['Perfect'],
            ['All good'],
        ];
    }

    #[DataProvider('nonThankYouMessages')]
    public function test_rejects_non_thank_you_messages(string $body): void
    {
        $this->assertFalse($this->detector->isThankYouNote($body));
    }

    public static function nonThankYouMessages(): array
    {
        return [
            ['Thanks, but the issue is still happening. Can you help?'],
            ['I still cannot log in. Please fix this.'],
            ['Thank you for the update. When will this be resolved?'],
            [str_repeat('a', 300)],
        ];
    }
}
