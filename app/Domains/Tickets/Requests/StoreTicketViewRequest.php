<?php

namespace App\Domains\Tickets\Requests;

use App\Domains\Tickets\Models\TicketView;
use App\Domains\Tickets\Support\TicketFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $filterRules = collect(TicketFilters::rules())
            ->mapWithKeys(fn (array $rules, string $key) => ["filters.{$key}" => $rules])
            ->all();

        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
            'is_default' => ['boolean'],
            'visibility' => ['required', Rule::in([
                TicketView::VISIBILITY_PRIVATE,
                TicketView::VISIBILITY_TEAM,
            ])],
            'team_id' => ['nullable', 'required_if:visibility,team', 'integer', 'exists:teams,id'],
        ], $filterRules);
    }
}
