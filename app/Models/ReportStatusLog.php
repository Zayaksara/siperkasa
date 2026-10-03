<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $report_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $actor_id
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class ReportStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['report_id', 'from_status', 'to_status', 'actor_id', 'note'];

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
