<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAssetImportRequest;
use App\Jobs\ImportAssetsJob;
use App\Models\AssetImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AssetImportController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', AssetImport::class);

        return Inertia::render('admin/assets/Imports', [
            'imports' => AssetImport::query()->with('user')->latest()->limit(20)->get()
                ->map(fn (AssetImport $import) => [
                    'id' => $import->id,
                    'filename' => $import->filename,
                    'status' => $import->status->value,
                    'status_label' => $import->status->label(),
                    'inserted' => $import->inserted,
                    'updated' => $import->updated,
                    'failed' => $import->failed,
                    'errors' => $import->errors ?? [],
                    'user' => $import->user?->name,
                    'created_at' => $import->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(StoreAssetImportRequest $request): RedirectResponse
    {
        Gate::authorize('create', AssetImport::class);

        $file = $request->file('file');
        $path = $file->storeAs('imports', Str::uuid().'.xlsx', 'local');

        abort_if($path === false, 500, 'Berkas gagal disimpan.');

        $import = AssetImport::create([
            'user_id' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'status' => AssetImportStatus::Queued,
        ]);

        ImportAssetsJob::dispatch($import->id, $path);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Berkas diterima. Impor sedang diproses.']);

        return to_route('admin.asset-imports.index');
    }
}
