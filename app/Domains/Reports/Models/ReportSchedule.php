<?php

namespace App\Domains\Reports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FORMAT_CSV = 'csv';

    public const FORMAT_PDF = 'pdf';

    protected $fillable = [
        'saved_report_id',
        'user_id',
        'frequency',
        'weekday',
        'send_hour',
        'format',
        'is_enabled',
        'last_sent_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'send_hour' => 'integer',
            'is_enabled' => 'boolean',
            'last_sent_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function savedReport(): BelongsTo
    {
        return $this->belongsTo(SavedReport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
