<?php

namespace App\Models;

use App\Enums\AttachmentType;
use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ticket_number
 * @property int $user_id
 * @property string $title
 * @property int $category_id
 * @property int $room_id
 * @property int|null $asset_id
 * @property string $description
 * @property Priority $user_priority
 * @property ReportStatus $status
 * @property int|null $admin_id
 * @property Priority|null $admin_priority
 * @property Carbon|null $sla_date
 * @property int|null $assigned_pic_id
 * @property string|null $rejection_reason
 * @property string|null $hold_reason
 * @property string|null $repair_action
 * @property Carbon|null $completed_on
 * @property string|null $repair_cost
 * @property string|null $vendor_name
 * @property Carbon|null $validated_at
 * @property Carbon|null $started_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $cancelled_at
 */
class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number', 'user_id', 'title', 'category_id', 'room_id', 'asset_id', 'description',
        'user_priority', 'status', 'admin_id', 'admin_priority', 'sla_date', 'assigned_pic_id',
        'rejection_reason', 'hold_reason', 'repair_action', 'completed_on', 'repair_cost', 'vendor_name',
        'validated_at', 'started_at', 'closed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'user_priority' => Priority::class,
            'admin_priority' => Priority::class,
            'sla_date' => 'datetime',
            'completed_on' => 'date',
            'repair_cost' => 'decimal:2',
            'validated_at' => 'datetime',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function assignedPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_pic_id')->withTrashed();
    }

    /** @return HasMany<ReportAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    /** @return HasMany<ReportAttachment, $this> */
    public function damagePhotos(): HasMany
    {
        return $this->attachments()->where('type', AttachmentType::Damage)->orderBy('position');
    }

    /** @return HasMany<ReportAttachment, $this> */
    public function completionPhotos(): HasMany
    {
        return $this->attachments()->where('type', AttachmentType::Completion)->orderBy('position');
    }

    /** @return HasMany<ReportStatusLog, $this> */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReportStatusLog::class)->orderBy('id');
    }

    /** @param Builder<Report> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Pic => $query->where('assigned_pic_id', $user->id),
            UserRole::User => $query->where('user_id', $user->id),
        };
    }

    /**
     * Eager-loads exactly what ReportPresenter::summary() reads.
     *
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeWithSummary(Builder $query): Builder
    {
        return $query->with(['category:id,name', 'room.building:id,name', 'user:id,name', 'assignedPic:id,name']);
    }

    /** @param Builder<Report> $query */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', ReportStatus::finalValues())
            ->whereNotNull('sla_date')
            ->where('sla_date', '<', now());
    }

    public function isOverdue(): bool
    {
        return ! $this->status->isFinal() && $this->sla_date !== null && $this->sla_date->isPast();
    }
}
