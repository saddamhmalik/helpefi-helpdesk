<?php

namespace App\Domains\Tenancy\Support;

use App\Domains\Tenancy\Models\TenantInfrastructure;

final class TenantInfrastructurePresenter
{
    public static function label(?TenantInfrastructure $infrastructure): string
    {
        if ($infrastructure === null) {
            return 'Managed';
        }

        $externalDatabase = $infrastructure->usesExternalDatabase();
        $externalStorage = $infrastructure->usesExternalStorage();

        if ($externalDatabase && $externalStorage) {
            return 'Full BYO';
        }

        if ($externalDatabase) {
            return 'BYO DB';
        }

        if ($externalStorage) {
            return 'BYO Storage';
        }

        return 'Managed';
    }

    public static function summary(?TenantInfrastructure $infrastructure): array
    {
        if ($infrastructure === null) {
            return [
                'label' => 'Managed',
                'status' => 'verified',
                'status_message' => null,
                'health_failure_count' => 0,
            ];
        }

        return [
            'label' => self::label($infrastructure),
            'status' => $infrastructure->status,
            'status_message' => $infrastructure->status_message,
            'health_failure_count' => (int) $infrastructure->health_failure_count,
            'last_verified_at' => $infrastructure->last_verified_at?->toIso8601String(),
        ];
    }
}
