<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('settings/Profile', [
            'canEditEmail' => $user->hasRole(UserRole::Admin),
            'portalProfile' => $user->isPortalAccount() ? [
                'faculty' => $user->faculty,
                'study_program' => $user->study_program,
                'degree_level' => $user->degree_level,
                'phone' => $user->phone,
            ] : null,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profil diperbarui.']);

        return to_route('profile.edit');
    }
}
