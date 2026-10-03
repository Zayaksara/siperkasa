<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $asset_code
 * @property string $kode_barang
 * @property int $nup
 * @property string $name
 * @property string|null $brand
 * @property string|null $type
 * @property string $jenis_bmn
 * @property string $golongan
 * @property string|null $condition
 * @property Carbon|null $acquired_at
 * @property string|null $acquisition_value
 * @property string|null $kode_register
 * @property int|null $category_id
 * @property array<string, mixed> $bmn_data
 */
class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code', 'kode_barang', 'nup', 'name', 'brand', 'type', 'jenis_bmn',
        'golongan', 'condition', 'acquired_at', 'acquisition_value', 'kode_register',
        'category_id', 'bmn_data',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
            'acquisition_value' => 'decimal:2',
            'bmn_data' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
