<?php

namespace Tests\Unit\Sla;

use App\Domains\Sla\Repositories\SlaEscalationRepository;
use PHPUnit\Framework\TestCase;

class SlaEscalationRepositoryTest extends TestCase
{
    public function test_logged_rule_keys_for_timers_returns_empty_for_no_ids(): void
    {
        $repository = new SlaEscalationRepository;

        $this->assertSame([], $repository->loggedRuleKeysForTimers([]));
    }

    public function test_rule_key_format_is_stable(): void
    {
        $this->assertSame('2:resolution', SlaEscalationRepository::ruleKey(2, 'resolution'));
    }
}
