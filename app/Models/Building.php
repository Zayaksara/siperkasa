<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $campus_name
 * @property int|null $sibermap_id
 * @property Carbon|null $synced_at
 */
class Building extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'campus_name', 'sibermap_id', 'synced_at'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function isSynced(): bool
    {
        return $this->sibermap_id !== null;
    }

    /** @return HasMany<Room, $this> */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function isInUse(): bool
    {
        return $this->rooms()->exists();
    }
}
