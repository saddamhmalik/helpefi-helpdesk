<?php

namespace App\Support;

class TenantCache
{
    public static function key(string $suffix): string
    {
        return 'tenant:'.tenant('id').':'.$suffix;
    }
}
