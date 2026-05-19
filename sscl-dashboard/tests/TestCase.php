<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedAccessControl(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function userWithRole(string $role = RolePermissionSeeder::PETUGAS_ROLE, array $attributes = []): User
    {
        $this->seedAccessControl();

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
