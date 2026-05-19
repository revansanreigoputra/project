<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $roleFilter = $filters['role'] ?? 'all';
        $perPage = in_array((int) ($filters['per_page'] ?? 10), [10, 25, 50], true)
            ? (int) ($filters['per_page'] ?? 10)
            : 10;

        $users = User::with(['roles', 'permissions'])
            ->withCount(['roles', 'permissions'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($roleFilter !== 'all', fn ($query) => $query->role($roleFilter))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('pages.admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'per_page' => $perPage,
            ],
            'perPageOptions' => [10, 25, 50],
        ]);
    }

    public function create()
    {
        return view('pages.admin.users.create', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validatedUser($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles($validated['roles'] ?? []);
        $user->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.users.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Pengguna Ditambahkan')
            ->with('toast_message', 'Akun '.$user->name.' berhasil dibuat beserta hak aksesnya.');
    }

    public function edit(User $user)
    {
        return view('pages.admin.users.edit', [
            ...$this->formData(),
            'user' => $user->load(['roles', 'permissions']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $this->validatedUser($request, $user);
        $roles = $validated['roles'] ?? [];

        if ($user->is($request->user()) && ! in_array('Admin', $roles, true)) {
            return back()
                ->withErrors(['roles' => 'Akun Anda sendiri harus tetap memiliki role Admin.'])
                ->withInput();
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);
        $user->syncRoles($roles);
        $user->syncPermissions($validated['permissions'] ?? []);

        return redirect()
            ->route('admin.users.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Pengguna Diperbarui')
            ->with('toast_message', 'Hak akses '.$user->name.' berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Tidak Bisa Menghapus Akun Sendiri')
                ->with('toast_message', 'Pilih admin lain untuk menghapus akun ini.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Pengguna Dihapus')
            ->with('toast_message', 'Akun '.$name.' berhasil dihapus.');
    }

    private function validatedUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
        ]);
    }

    private function formData(): array
    {
        $permissions = Permission::query()->orderBy('name')->get();

        return [
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->get(),
            'permissionGroups' => $permissions->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->headline()->toString()),
        ];
    }
}
