<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ImportUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportUsersRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class UserImportController extends Controller
{
    public function store(ImportUsersRequest $request, ImportUsers $importer): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $file = $request->file('file');

        $result = $importer->handle($file->getRealPath(), $file->getClientOriginalExtension());

        return to_route('admin.users.index')->with('import_result', $result);
    }
}
