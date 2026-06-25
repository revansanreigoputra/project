<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleManagementController extends Controller
{
    // Tampilkan daftar role beserta filter pencarian dan jumlah user/permission.
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = in_array((int) ($filters['per_page'] ?? 10), [10, 25, 50], true)
            ? (int) ($filters['per_page'] ?? 10)
            : 10;

        $roles = Role::with('permissions')
            ->withCount(['permissions', 'users'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('pages.admin.roles.index', [
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'perPageOptions' => [10, 25, 50],
            'systemRoles' => $this->systemRoles(),
        ]);
    }

    // Tampilkan form tambah role dengan daftar permission.
    public function create()
    {
        return view('pages.admin.roles.create', $this->formData());
    }

    // Buat role baru lalu sinkronkan permission yang dipilih.
    public function store(Request $request)
    {
        $validated = $this->validatedRole($request);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Role Ditambahkan')
            ->with('toast_message', 'Role '.$role->name.' berhasil dibuat.');
    }

    // Tampilkan form edit role dan tandai role sistem yang namanya dilindungi.
    public function edit(Role $role)
    {
        return view('pages.admin.roles.edit', [
            ...$this->formData(),
            'role' => $role->load('permissions'),
            'isSystemRole' => in_array($role->name, $this->systemRoles(), true),
        ]);
    }

    // Update role, tapi nama role sistem tetap dikunci supaya route tidak rusak.
    public function update(Request $request, Role $role)
    {
        $validated = $this->validatedRole($request, $role);
        $oldName = $role->name;

        if (! in_array($role->name, $this->systemRoles(), true)) {
            $role->update(['name' => $validated['name']]);
        }

        $role->syncPermissions($validated['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Role Diperbarui')
            ->with('toast_message', 'Hak akses role '.$oldName.' berhasil diperbarui.');
    }

    // Hapus role custom dan lindungi role bawaan aplikasi.
    public function destroy(Role $role)
    {
        if (in_array($role->name, $this->systemRoles(), true)) {
            return back()
                ->with('toast_type', 'danger')
                ->with('toast_title', 'Role Sistem Dilindungi')
                ->with('toast_message', 'Role Admin dan Petugas tidak dapat dihapus.');
        }

        $name = $role->name;
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('toast_type', 'success')
            ->with('toast_title', 'Role Dihapus')
            ->with('toast_message', 'Role '.$name.' berhasil dihapus.');
    }

    // Validasi nama role dan daftar permission yang boleh dipasang.
    private function validatedRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'web'))
                    ->ignore($role?->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
        ]);
    }

    // Siapkan permission yang dikelompokkan berdasarkan modul untuk form role.
    private function formData(): array
    {
        $permissions = Permission::query()->orderBy('name')->get();

        return [
            'permissionGroups' => $permissions->groupBy(fn (Permission $permission) => str($permission->name)->before('.')->headline()->toString()),
        ];
    }

    // Daftar role bawaan yang tidak boleh dihapus atau diganti namanya.
    private function systemRoles(): array
    {
        return [
            RolePermissionSeeder::ADMIN_ROLE,
            RolePermissionSeeder::PETUGAS_ROLE,
        ];
    }
}
