<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionManagementController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $module = $filters['module'] ?? 'all';
        $perPage = in_array((int) ($filters['per_page'] ?? 10), [10, 25, 50], true)
            ? (int) ($filters['per_page'] ?? 10)
            : 10;

        $permissions = Permission::with('roles')
            ->withCount(['roles', 'users'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($module !== 'all', fn ($query) => $query->where('name', 'like', $module.'.%'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $modules = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name) => str($name)->before('.')->toString())
            ->unique()
            ->values();

        return view('pages.admin.permissions.index', [
            'permissions' => $permissions,
            'modules' => $modules,
            'filters' => [
                'search' => $search,
                'module' => $module,
                'per_page' => $perPage,
            ],
            'perPageOptions' => [10, 25, 50],
            'systemPermissions' => RolePermissionSeeder::PERMISSIONS,
        ]);
    }

    public function create()
    {
        return view('pages.admin.permissions.create', $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPermission($request);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);
        $permission->syncRoles($validated['roles'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.permissions.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Permission Ditambahkan')
            ->with('toast_message', 'Permission '.$permission->name.' berhasil dibuat.');
    }

    public function edit(Permission $permission)
    {
        return view('pages.admin.permissions.edit', [
            ...$this->formData(),
            'permission' => $permission->load('roles'),
            'isSystemPermission' => in_array($permission->name, RolePermissionSeeder::PERMISSIONS, true),
        ]);
    }

    public function update(Request $request, Permission $permission)
    {
        $validated = $this->validatedPermission($request, $permission);
        $oldName = $permission->name;

        if (! in_array($permission->name, RolePermissionSeeder::PERMISSIONS, true)) {
            $permission->update(['name' => $validated['name']]);
        }

        $permission->syncRoles($validated['roles'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.permissions.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Permission Diperbarui')
            ->with('toast_message', 'Relasi permission '.$oldName.' berhasil diperbarui.');
    }

    public function destroy(Permission $permission)
    {
        if (in_array($permission->name, RolePermissionSeeder::PERMISSIONS, true)) {
            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Permission Sistem Dilindungi')
                ->with('toast_message', 'Permission bawaan dipakai oleh route dan tidak dapat dihapus.');
        }

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Permission Masih Dipakai')
                ->with('toast_message', 'Lepaskan permission dari role atau user sebelum dihapus.');
        }

        $name = $permission->name;
        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.permissions.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Permission Dihapus')
            ->with('toast_message', 'Permission '.$name.' berhasil dihapus.');
    }

    private function validatedPermission(Request $request, ?Permission $permission = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('permissions', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'web'))
                    ->ignore($permission?->id),
            ],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
        ]);
    }

    private function formData(): array
    {
        return [
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->get(),
        ];
    }
}
