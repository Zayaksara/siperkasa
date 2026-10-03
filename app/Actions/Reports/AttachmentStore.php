<?php

namespace App\Actions\Reports;

use App\Enums\AttachmentType;
use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class AttachmentStore
{
    /**
     * Stores the files and creates their rows. Deletes the stored files again if anything fails.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function add(Report $report, AttachmentType $type, array $files): void
    {
        $paths = $this->putFiles($report, $type, $files);

        try {
            $this->addRows($report, $type, $paths);
        } catch (Throwable $e) {
            $this->deleteFiles($paths);

            throw $e;
        }
    }

    /**
     * Writes the files to the private disk without touching the database.
     *
     * @param  array<int, UploadedFile>  $files
     * @return list<string> stored paths
     */
    public function putFiles(Report $report, AttachmentType $type, array $files): array
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
    public function addRows(Report $report, AttachmentType $type, array $paths): void
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
     * Does not touch files: delete the returned paths after the surrounding transaction commits.
     *
     * @param  array<int, int|string>  $ids
     * @return list<string> paths of the removed attachments
     */
    public function remove(Report $report, AttachmentType $type, array $ids): array
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
    public function deleteFiles(array $paths): void
    {
        Storage::disk('local')->delete($paths);
    }
}
