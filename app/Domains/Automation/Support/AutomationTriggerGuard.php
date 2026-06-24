<?php

namespace App\Domains\Automation\Support;

class AutomationTriggerGuard
{
    public static function shouldSkip(array $context): bool
    {
        if ($context['from_automation'] ?? false) {
            return true;
        }

        return ($context['source'] ?? null) === 'escalation';
    }
}
