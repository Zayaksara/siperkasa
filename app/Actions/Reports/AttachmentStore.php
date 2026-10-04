<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Models\Report;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class AttachmentStore
{
    /**
     * Runs $work in one transaction together with the attachment changes, and keeps files and rows consistent.
     *
     * New files are written first (outside the transaction). Inside it $work runs, the rows of $removeIds are
     * deleted and the rows of the new files are added. Removed files are deleted after commit; on any failure
     * the new files are deleted again and the exception is rethrown.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, int|string>  $removeIds
     */
    public function within(Report $report, AttachmentType $type, array $files, array $removeIds, Closure $work): mixed
    {
        $paths = $this->putFiles($report, $type, $files);

        try {
            $result = DB::transaction(function () use ($report, $type, $paths, $removeIds, $work) {
                $result = $work();
                $removed = $this->remove($report, $type, $removeIds);
                $this->addRows($report, $type, $paths);
                DB::afterCommit(fn () => $this->deleteFiles($removed));

                return $result;
            });
        } catch (Throwable $e) {
            $this->deleteFiles($paths);

            throw $e;
        }

        return $result;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<string> stored paths
     */
    private function putFiles(Report $report, AttachmentType $type, array $files): array
    {
        $paths = [];

        try {
            foreach ($files as $file) {
                $path = $file->store("reports/{$report->id}/{$type->value}", 'local');

                if ($path === false) {
                    throw new RuntimeException('Failed to store the uploaded photo.');
                }

                $paths[] = $path;
            }
        } catch (Throwable $e) {
            $this->deleteFiles($paths);

            throw $e;
        }

        return $paths;
    }

    /** @param  list<string>  $paths  already stored files */
    private function addRows(Report $report, AttachmentType $type, array $paths): void
    {
        $position = (int) $report->attachments()->where('type', $type)->max('position');

        foreach ($paths as $path) {
            $report->attachments()->create([
                'type' => $type,
                'path' => $path,
                'position' => ++$position,
            ]);
        }
    }

    /**
     * Deletes the rows of the given attachments, but only if they belong to this report and type.
     *
     * @param  array<int, int|string>  $ids
     * @return list<string> paths of the removed attachments
     */
    private function remove(Report $report, AttachmentType $type, array $ids): array
    {
        $paths = [];

        $report->attachments()->where('type', $type)->whereIn('id', $ids)->get()
            ->each(function ($attachment) use (&$paths) {
                $paths[] = $attachment->path;
                $attachment->delete();
            });

        return $paths;
    }

    /** @param  list<string>  $paths */
    private function deleteFiles(array $paths): void
    {
        Storage::disk('local')->delete($paths);
    }
}
