<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // lists are narrowed with Report::visibleTo()
    }

    public function view(User $user, Report $report): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::User => $report->user_id === $user->id,
            UserRole::Pic => $report->assigned_pic_id === $user->id,
        };
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::User);
    }

    public function update(User $user, Report $report): bool
    {
        return $this->ownsWhilePending($user, $report);
    }

    public function cancel(User $user, Report $report): bool
    {
        return $this->ownsWhilePending($user, $report);
    }

    public function validate(User $user, Report $report): bool
    {
        return $user->hasRole(UserRole::Admin) && $report->status === ReportStatus::Pending;
    }

    public function reject(User $user, Report $report): bool
    {
        return $this->validate($user, $report);
    }

    public function reassign(User $user, Report $report): bool
    {
        return $user->hasRole(UserRole::Admin)
            && in_array($report->status, [ReportStatus::Assigned, ReportStatus::InProgress, ReportStatus::OnHold], true);
    }

    public function start(User $user, Report $report): bool
    {
        return $this->isAssignedPic($user, $report) && $report->status === ReportStatus::Assigned;
    }

    public function hold(User $user, Report $report): bool
    {
        return $this->isAssignedPic($user, $report) && $report->status === ReportStatus::InProgress;
    }

    public function resume(User $user, Report $report): bool
    {
        return $this->isAssignedPic($user, $report) && $report->status === ReportStatus::OnHold;
    }

    public function complete(User $user, Report $report): bool
    {
        return $this->isAssignedPic($user, $report) && $report->status === ReportStatus::InProgress;
    }

    private function ownsWhilePending(User $user, Report $report): bool
    {
        return $user->hasRole(UserRole::User)
            && $report->user_id === $user->id
            && $report->status === ReportStatus::Pending;
    }

    private function isAssignedPic(User $user, Report $report): bool
    {
        return $user->hasRole(UserRole::Pic) && $report->assigned_pic_id === $user->id;
    }
}
