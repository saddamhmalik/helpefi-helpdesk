<?php

namespace App\Domains\Tickets\Services;

use App\Domains\Brands\Models\Brand;
use App\Domains\Brands\Services\BrandService;
use App\Domains\Settings\Repositories\HelpdeskSettingRepository;
use App\Domains\Tickets\Models\Ticket;
use App\Domains\Tickets\Models\TicketNumberSequence;
use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    public function __construct(
        private HelpdeskSettingRepository $helpdeskSettings,
        private BrandService $brands,
    ) {
    }

    public function next(?int $brandId = null): string
    {
        $prefix = $this->resolvePrefix($brandId);

        $sequence = DB::transaction(function () use ($brandId, $prefix) {
            $row = TicketNumberSequence::query()
                ->where('brand_id', $brandId)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $row->increment('last_value');

                return (int) $row->fresh()->last_value;
            }

            $initial = $this->bootstrapSequence($brandId, $prefix);

            TicketNumberSequence::query()->create([
                'brand_id' => $brandId,
                'last_value' => $initial,
            ]);

            return $initial;
        });

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function syncFromExistingTickets(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('ticket_number_sequences')
            || ! \Illuminate\Support\Facades\Schema::hasTable('tickets')) {
            return 0;
        }

        $created = 0;

        foreach (Ticket::query()->select('brand_id')->distinct()->pluck('brand_id') as $brandId) {
            if (TicketNumberSequence::query()->where('brand_id', $brandId)->exists()) {
                continue;
            }

            $prefix = $this->resolvePrefix($brandId);
            $nextValue = $this->bootstrapSequence($brandId, $prefix);

            TicketNumberSequence::query()->create([
                'brand_id' => $brandId,
                'last_value' => $nextValue,
            ]);

            $created++;
        }

        if (! Ticket::query()->exists()
            && ! TicketNumberSequence::query()->whereNull('brand_id')->exists()) {
            TicketNumberSequence::query()->create([
                'brand_id' => null,
                'last_value' => 1,
            ]);
            $created++;
        }

        return $created;
    }

    private function bootstrapSequence(?int $brandId, string $prefix): int
    {
        $prefixLength = strlen($prefix);

        $maxSequence = (int) Ticket::query()
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->where('number', 'like', $prefix.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(number, ?) AS UNSIGNED)) as max_seq', [$prefixLength + 1])
            ->value('max_seq');

        return max(1, $maxSequence + 1);
    }

    private function resolvePrefix(?int $brandId): string
    {
        if ($brandId) {
            $brand = Brand::query()->find($brandId);

            if ($brand) {
                $prefix = $this->brands->ticketNumberPrefix($brand);

                if ($prefix) {
                    return $prefix;
                }
            }
        }

        return $this->helpdeskSettings->current()->ticket_number_prefix ?: 'HD-';
    }
}
