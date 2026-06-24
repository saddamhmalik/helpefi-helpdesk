<?php

namespace App\Domains\Tickets\Requests;

use App\Domains\ServiceCatalog\Models\ServiceCatalogItem;
use App\Domains\Workforce\Services\WorkforceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TicketFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('type') && blank($this->input('type'))) {
            $this->merge(['type' => ServiceCatalogItem::TYPE_INCIDENT]);
        }
    }

    protected function assignableAgentIds(): array
    {
        return app(WorkforceService::class)->assignableAgentIds();
    }

    protected function assignableAgentRule(): array
    {
        return ['nullable', Rule::in($this->assignableAgentIds())];
    }
}
