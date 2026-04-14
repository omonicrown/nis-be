<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    use ApiResponse;

    /**
     * List all roles.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::withCount('users')
            ->with('permissions:id,name,slug,group')
            ->get()
            ->map(fn($r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'slug'        => $r->slug,
                'description' => $r->description,
                'users_count' => $r->users_count,
                'permissions' => $r->permissions->map(fn($p) => [
                    'id'    => $p->id,
                    'name'  => $p->name,
                    'slug'  => $p->slug,
                    'group' => $p->group,
                ]),
            ]);

        return $this->success($roles);
    }

    /**
     * List all permissions (grouped).
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all()
            ->groupBy('group')
            ->map(fn($group) => $group->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'slug'  => $p->slug,
            ]));

        return $this->success($permissions);
    }

    /**
     * List all admin/executive users with their roles and permissions.
     */
    public function admins(): JsonResponse
    {
        $admins = User::whereHas('role', fn($q) => $q->whereIn('slug', ['super_admin', 'admin']))
            ->with(['role.permissions', 'membershipCategory'])
            ->get()
            ->map(fn($u) => [
                'id'                  => $u->id,
                'full_name'           => $u->full_name,
                'email'               => $u->email,
                'phone'               => $u->phone,
                'nis_membership_id'   => $u->nis_membership_id,
                'membership_category' => $u->membershipCategory?->name,
                'designation'         => $u->membershipCategory?->designation,
                'role'                => [
                    'id'   => $u->role->id,
                    'name' => $u->role->name,
                    'slug' => $u->role->slug,
                ],
                'permissions' => $u->role?->permissions->map(fn($p) => [
                    'id'    => $p->id,
                    'name'  => $p->name,
                    'slug'  => $p->slug,
                    'group' => $p->group,
                ]),
            ]);

        return $this->success($admins);
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $user->update(['role_id' => $validated['role_id']]);
        $user->load('role.permissions');

        return $this->success([
            'user_id'   => $user->id,
            'full_name' => $user->full_name,
            'role'      => $user->role->name,
            'permissions' => $user->role->permissions->pluck('slug'),
        ], "Role assigned to {$user->full_name}.");
    }

    /**
     * Update permissions for a role.
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->slug === 'super_admin') {
            return $this->error('Cannot modify Super Admin permissions. Super Admin has all permissions by default.', 403);
        }

        $validated = $request->validate([
            'permission_ids'   => ['required', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permission_ids']);
        $role->load('permissions');

        return $this->success([
            'role'        => $role->name,
            'permissions' => $role->permissions->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'slug'  => $p->slug,
                'group' => $p->group,
            ]),
        ], "Permissions updated for {$role->name}.");
    }

    /**
     * Create a custom role.
     */
    public function createRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'slug'             => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'description'      => ['nullable', 'string', 'max:500'],
            'permission_ids'   => ['nullable', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name'        => $validated['name'],
            'slug'        => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        $role->load('permissions');

        return $this->created([
            'id'          => $role->id,
            'name'        => $role->name,
            'slug'        => $role->slug,
            'permissions' => $role->permissions->pluck('slug'),
        ], 'Role created.');
    }

    /**
     * Get a single user's permissions (what modules they can access).
     */
    public function userPermissions(User $user): JsonResponse
    {
        $user->load('role.permissions');

        $isSuperAdmin = $user->role?->slug === 'super_admin';

        // Super admin gets all permissions
        $permissions = $isSuperAdmin
            ? Permission::all()
            : ($user->role?->permissions ?? collect());

        $grouped = $permissions->groupBy('group')->map(fn($group) => $group->pluck('slug'));

        return $this->success([
            'user_id'        => $user->id,
            'full_name'      => $user->full_name,
            'role'           => $user->role?->name,
            'is_super_admin' => $isSuperAdmin,
            'permissions'    => $grouped,
            'modules'        => $this->mapPermissionsToModules($permissions->pluck('slug')->toArray()),
        ]);
    }

    /**
     * Map permission slugs to frontend modules.
     */
    private function mapPermissionsToModules(array $permissionSlugs): array
    {
        $moduleMap = [
            'members'       => ['manage_members', 'view_members', 'approve_members'],
            'meetings'      => ['manage_meetings', 'view_meetings', 'manage_attendance'],
            'payments'      => ['manage_payments', 'view_payments', 'verify_payments'],
            'content'       => ['manage_posts', 'manage_events', 'manage_resources', 'manage_announcements'],
            'forum'         => ['manage_forum'],
            'feedback'      => ['manage_feedback'],
            'reports'       => ['view_reports', 'export_reports'],
            'settings'      => ['manage_settings', 'manage_roles'],
            'import_export' => ['import_members', 'export_members'],
        ];

        $modules = [];
        foreach ($moduleMap as $module => $requiredPerms) {
            $modules[$module] = count(array_intersect($permissionSlugs, $requiredPerms)) > 0;
        }

        return $modules;
    }
}
