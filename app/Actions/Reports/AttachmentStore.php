<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class AttachmentStore
{
    /** @param  array<int, UploadedFile>  $files */
    public function add(Report $report, AttachmentType $type, array $files): void
    {
        $position = (int) $report->attachments()->where('type', $type)->max('position');

        foreach ($files as $file) {
            $path = $file->store("reports/{$report->id}/{$type->value}", 'local');

            $report->attachments()->create([
                'type' => $type,
                'path' => $path,
                'position' => ++$position,
            ]);
        }
    }

    /**
     * Deletes the given attachments, but only if they belong to this report and type.
     *
     * @param  array<int, int|string>  $ids
     */
    public function remove(Report $report, AttachmentType $type, array $ids): void
    {
        $report->attachments()->where('type', $type)->whereIn('id', $ids)->get()
            ->each(function ($attachment) {
                Storage::disk('local')->delete($attachment->path);
                $attachment->delete();
            });
    }
}
