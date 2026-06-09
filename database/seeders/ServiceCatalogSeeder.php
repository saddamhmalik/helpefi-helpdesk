<?php

namespace Database\Seeders;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\ServiceCatalog\Models\ServiceCategory;
use App\Domains\Tickets\Models\TicketPriority;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $normalPriorityId = TicketPriority::query()->where('slug', 'normal')->value('id');

        $itCategory = ServiceCategory::query()->firstOrCreate(
            ['slug' => 'it-support'],
            [
                'name' => 'IT Support',
                'description' => 'Hardware, software, and access requests.',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        ServiceCatalogItem::query()->firstOrCreate(
            ['slug' => 'password-reset'],
            [
                'service_category_id' => $itCategory->id,
                'name' => 'Password reset',
                'description' => 'Request a password reset for corporate accounts.',
                'ticket_type' => ServiceCatalogItem::TYPE_INCIDENT,
                'ticket_priority_id' => $normalPriorityId,
                'fields' => [
                    ['name' => 'account', 'label' => 'Account email', 'type' => 'text', 'required' => true, 'options' => []],
                ],
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ],
        );

        ServiceCatalogItem::query()->firstOrCreate(
            ['slug' => 'new-laptop'],
            [
                'service_category_id' => $itCategory->id,
                'name' => 'New laptop request',
                'description' => 'Request a laptop for a new hire or replacement.',
                'ticket_type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
                'ticket_priority_id' => $normalPriorityId,
                'fields' => [
                    ['name' => 'employee', 'label' => 'Employee name', 'type' => 'text', 'required' => true, 'options' => []],
                    ['name' => 'location', 'label' => 'Office location', 'type' => 'select', 'required' => true, 'options' => ['HQ', 'Remote', 'Branch']],
                ],
                'sort_order' => 2,
                'is_public' => true,
                'is_active' => true,
            ],
        );

        $hrCategory = ServiceCategory::query()->firstOrCreate(
            ['slug' => 'hr-services'],
            [
                'name' => 'HR Services',
                'description' => 'People operations and workplace requests.',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        ServiceCatalogItem::query()->firstOrCreate(
            ['slug' => 'pto-request'],
            [
                'service_category_id' => $hrCategory->id,
                'name' => 'PTO request',
                'description' => 'Submit paid time off for manager approval.',
                'ticket_type' => ServiceCatalogItem::TYPE_SERVICE_REQUEST,
                'ticket_priority_id' => $normalPriorityId,
                'fields' => [
                    ['name' => 'dates', 'label' => 'Requested dates', 'type' => 'text', 'required' => true, 'options' => []],
                    ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => false, 'options' => []],
                ],
                'sort_order' => 1,
                'is_public' => true,
                'is_active' => true,
            ],
        );
    }
}
