<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $asset_code
 * @property string $name
 * @property string|null $brand_type
 * @property int|null $category_id
 * @property string|null $building
 * @property string|null $room
 * @property int|null $default_pic_id
 * @property int|null $purchase_year
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code',
        'name',
        'brand_type',
        'category_id',
        'building',
        'room',
        'default_pic_id',
        'purchase_year',
    ];

    /**
     * Kategori aset.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * PIC default yang bertanggung jawab atas aset ini.
     */
    public function defaultPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_pic_id');
    }

    /**
     * Laporan kerusakan yang terkait dengan aset ini.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
