<?php

namespace App\Models;

use App\Enums\AssetImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $filename
 * @property AssetImportStatus $status
 * @property int $inserted
 * @property int $updated
 * @property int $failed
 * @property array<int, array{line: int, message: string}>|null $errors
 */
class AssetImport extends Model
{
    protected $fillable = ['user_id', 'filename', 'status', 'inserted', 'updated', 'failed', 'errors'];

    protected function casts(): array
    {
        return [
            'status' => AssetImportStatus::class,
            'errors' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
