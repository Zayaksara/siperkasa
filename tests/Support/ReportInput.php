<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Room;
use Illuminate\Http\UploadedFile;

final class ReportInput
{
    /** A valid report form payload (with one fake damage photo). */
    public static function valid(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Proyektor tidak menyala',
            'category_id' => Category::factory()->create()->id,
            'room_id' => Room::factory()->create()->id,
            'asset_id' => null,
            'description' => 'Proyektor di ruang 101 tidak menyala sejak pagi.',
            'user_priority' => 'High',
            'photos' => [UploadedFile::fake()->image('rusak.jpg')],
        ], $overrides);
    }
}
