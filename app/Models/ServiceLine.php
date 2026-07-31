<?php

namespace App\Models;

use Database\Factories\ServiceLineFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ServiceLine extends Model
{
    /** @use HasFactory<ServiceLineFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'maintenance_event_id',
        'serviceable_type',
        'serviceable_id',
        'line_cost',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['line_cost' => 'decimal:2'];
    }

    /** @return BelongsTo<MaintenanceEvent, $this> */
    public function maintenanceEvent(): BelongsTo
    {
        return $this->belongsTo(MaintenanceEvent::class);
    }

    /** @return MorphTo<AbstractService, $this> */
    public function serviceable(): MorphTo
    {
        return $this->morphTo();
    }
}
