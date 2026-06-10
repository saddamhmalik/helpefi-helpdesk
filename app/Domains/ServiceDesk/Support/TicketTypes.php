<?php

namespace App\Domains\ServiceDesk\Support;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;

class TicketTypes
{
    public static function all(): array
    {
        return [
            [
                'value' => ServiceCatalogItem::TYPE_INCIDENT,
                'label' => 'Incidents',
                'singular' => 'Incident',
                'description' => 'Unplanned interruptions and service degradations.',
            ],
            [
                'value' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
                'label' => 'Service requests',
                'singular' => 'Service request',
                'description' => 'Standard requests from the service catalog.',
            ],
            [
                'value' => ServiceCatalogItem::TYPE_CHANGE,
                'label' => 'Changes',
                'singular' => 'Change',
                'description' => 'Planned changes to infrastructure or services.',
            ],
            [
                'value' => ServiceCatalogItem::TYPE_PROBLEM,
                'label' => 'Problems',
                'singular' => 'Problem',
                'description' => 'Root-cause analysis for recurring incidents.',
            ],
        ];
    }

    public static function values(): array
    {
        return array_column(self::all(), 'value');
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::values(), true);
    }

    public static function find(string $type): ?array
    {
        foreach (self::all() as $item) {
            if ($item['value'] === $type) {
                return $item;
            }
        }

        return null;
    }
}
