<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $default_pic_id
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'default_pic_id'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function defaultPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_pic_id');
    }

    /** True when deleting this category would orphan data (extended in Plan 3 with reports). */
    public function isInUse(): bool
    {
        return $this->assets()->exists() || $this->reports()->exists();
    }
}
