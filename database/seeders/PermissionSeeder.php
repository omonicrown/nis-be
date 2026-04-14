<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ────────────────────────────────────
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Full system access. All permissions by default.'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrative access with assigned permissions.'],
            ['name' => 'Member', 'slug' => 'member', 'description' => 'Regular member access.'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        // ── Permissions (grouped by module) ──────────
        $permissions = [
            // Members
            ['name' => 'View Members', 'slug' => 'view_members', 'group' => 'members'],
            ['name' => 'Manage Members', 'slug' => 'manage_members', 'group' => 'members'],
            ['name' => 'Approve Members', 'slug' => 'approve_members', 'group' => 'members'],

            // Meetings
            ['name' => 'View Meetings', 'slug' => 'view_meetings', 'group' => 'meetings'],
            ['name' => 'Manage Meetings', 'slug' => 'manage_meetings', 'group' => 'meetings'],
            ['name' => 'Manage Attendance', 'slug' => 'manage_attendance', 'group' => 'meetings'],

            // Payments
            ['name' => 'View Payments', 'slug' => 'view_payments', 'group' => 'payments'],
            ['name' => 'Manage Payments', 'slug' => 'manage_payments', 'group' => 'payments'],
            ['name' => 'Verify Payments', 'slug' => 'verify_payments', 'group' => 'payments'],

            // Content
            ['name' => 'Manage Posts', 'slug' => 'manage_posts', 'group' => 'content'],
            ['name' => 'Manage Events', 'slug' => 'manage_events', 'group' => 'content'],
            ['name' => 'Manage Resources', 'slug' => 'manage_resources', 'group' => 'content'],
            ['name' => 'Manage Announcements', 'slug' => 'manage_announcements', 'group' => 'content'],

            // Forum
            ['name' => 'Manage Forum', 'slug' => 'manage_forum', 'group' => 'forum'],

            // Feedback
            ['name' => 'Manage Feedback', 'slug' => 'manage_feedback', 'group' => 'feedback'],

            // Reports
            ['name' => 'View Reports', 'slug' => 'view_reports', 'group' => 'reports'],
            ['name' => 'Export Reports', 'slug' => 'export_reports', 'group' => 'reports'],

            // Settings & Roles
            ['name' => 'Manage Roles', 'slug' => 'manage_roles', 'group' => 'settings'],
            ['name' => 'Manage Settings', 'slug' => 'manage_settings', 'group' => 'settings'],

            // Import/Export
            ['name' => 'Import Members', 'slug' => 'import_members', 'group' => 'import_export'],
            ['name' => 'Export Members', 'slug' => 'export_members', 'group' => 'import_export'],

            // Executives
            ['name' => 'Manage Executives', 'slug' => 'manage_executives', 'group' => 'executives'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // ── Assign ALL permissions to Admin role ─────
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync(Permission::pluck('id'));
        }
    }
}
