<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:user,admin,pic'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->when($filters['q'] ?? null, function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('nim_nip', 'like', $like));
            })
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'nim_nip' => $user->nim_nip,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()]),
            'importResult' => session('import_result'),
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        User::create([...$request->validated(), 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna ditambahkan.']);

        return back();
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);
        $user->clearDefaultPicIfNotPic();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna diperbarui.']);

        return back();
    }

    public function active(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->withErrors(['user' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
        }

        $user->update(['is_active' => $data['is_active']]);
        $user->clearDefaultPicIfNotPic();

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['is_active'] ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.']);

        return back();
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:255']]);

        $user->update(['password' => $data['password']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password diganti.']);

        return back();
    }
}
