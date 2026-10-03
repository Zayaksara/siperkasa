<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $building_id
 * @property string $code
 * @property string $name
 * @property int|null $floor
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = ['building_id', 'code', 'name', 'floor'];

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
