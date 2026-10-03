<?php

namespace App\Models;

use App\Enums\AttachmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $report_id
 * @property AttachmentType $type
 * @property string $path
 * @property int $position
 */
class ReportAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['report_id', 'type', 'path', 'position'];

    protected function casts(): array
    {
        return ['type' => AttachmentType::class];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
