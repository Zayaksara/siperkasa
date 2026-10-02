<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ticket_number
 * @property int|null $user_id
 * @property int|null $asset_id
 * @property string|null $description
 * @property string|null $damage_photo
 * @property string|null $user_priority
 * @property string $status
 * @property int|null $admin_id
 * @property string|null $admin_priority
 * @property \Illuminate\Support\Carbon|null $sla_date
 * @property int|null $assigned_pic_id
 * @property string|null $rejection_reason
 * @property string|null $hold_reason
 * @property string|null $vendor_name
 * @property string|null $repair_action
 * @property float|null $repair_cost
 * @property string|null $completion_photo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'asset_id',
        'description',
        'damage_photo',
        'user_priority',
        'status',
        'admin_id',
        'admin_priority',
        'sla_date',
        'assigned_pic_id',
        'rejection_reason',
        'hold_reason',
        'vendor_name',
        'repair_action',
        'repair_cost',
        'completion_photo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sla_date' => 'datetime',
            'repair_cost' => 'decimal:2',
        ];
    }

    /**
     * User yang membuat laporan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Aset yang dilaporkan rusak.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Admin yang memvalidasi laporan.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * PIC yang ditugaskan menangani laporan.
     */
    public function assignedPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_pic_id');
    }
}
