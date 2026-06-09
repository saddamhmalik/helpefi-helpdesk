<?php

namespace App\Domains\Assets\Models;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignmentLog extends Model
{
    public const ACTION_ASSIGNED = 'assigned';

    public const ACTION_UNASSIGNED = 'unassigned';

    public const ACTION_ORGANIZATION_CHANGED = 'organization_changed';

    protected $fillable = [
        'asset_id',
        'contact_id',
        'organization_id',
        'changed_by',
        'action',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
