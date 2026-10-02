<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Aset yang termasuk dalam kategori ini.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
