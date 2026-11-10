<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $building_id
 * @property string $code
 * @property string $name
 * @property int|null $floor
 * @property int|null $sibermap_id
 * @property string|null $floor_name
 * @property Carbon|null $synced_at
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = ['building_id', 'code', 'name', 'floor', 'sibermap_id', 'floor_name', 'synced_at'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function isSynced(): bool
    {
        return $this->sibermap_id !== null;
    }

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** Extended in Plan 3 with reports. */
    public function isInUse(): bool
    {
        return $this->reports()->exists();
    }
}
