<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Roles ──────────────────────────────────────────
        $superAdmin = Role::create([
            'name'        => 'Super Admin',
            'slug'        => 'super_admin',
            'description' => 'Full system access. Can manage all settings and users.',
        ]);

        $admin = Role::create([
            'name'        => 'Admin',
            'slug'        => 'admin',
            'description' => 'Administrative access. Can manage members, content, and reports.',
        ]);

        $member = Role::create([
            'name'        => 'Member',
            'slug'        => 'member',
            'description' => 'Standard verified member. Can access dashboard, meetings, payments.',
        ]);

        // ─── Permissions ────────────────────────────────────

        $permissions = [
            // Members
            ['name' => 'View Members',        'slug' => 'view_members',        'group' => 'members'],
            ['name' => 'Manage Members',       'slug' => 'manage_members',       'group' => 'members'],
            ['name' => 'Approve Members',      'slug' => 'approve_members',      'group' => 'members'],
            ['name' => 'Suspend Members',      'slug' => 'suspend_members',      'group' => 'members'],

            // Meetings
            ['name' => 'View Meetings',        'slug' => 'view_meetings',        'group' => 'meetings'],
            ['name' => 'Manage Meetings',      'slug' => 'manage_meetings',      'group' => 'meetings'],
            ['name' => 'Take Attendance',      'slug' => 'take_attendance',      'group' => 'meetings'],
            ['name' => 'Manage Minutes',       'slug' => 'manage_minutes',       'group' => 'meetings'],

            // Payments
            ['name' => 'View Payments',        'slug' => 'view_payments',        'group' => 'payments'],
            ['name' => 'Manage Payments',      'slug' => 'manage_payments',      'group' => 'payments'],
            ['name' => 'Confirm Manual Pay',   'slug' => 'confirm_manual_payment', 'group' => 'payments'],

            // Content
            ['name' => 'View Content',         'slug' => 'view_content',         'group' => 'content'],
            ['name' => 'Manage Blog',          'slug' => 'manage_blog',          'group' => 'content'],
            ['name' => 'Manage Announcements', 'slug' => 'manage_announcements', 'group' => 'content'],
            ['name' => 'Manage Resources',     'slug' => 'manage_resources',     'group' => 'content'],

            // Events
            ['name' => 'View Events',          'slug' => 'view_events',          'group' => 'events'],
            ['name' => 'Manage Events',        'slug' => 'manage_events',        'group' => 'events'],

            // Reports
            ['name' => 'View Reports',         'slug' => 'view_reports',         'group' => 'reports'],
            ['name' => 'Export Reports',        'slug' => 'export_reports',       'group' => 'reports'],

            // Settings
            ['name' => 'Manage Roles',         'slug' => 'manage_roles',         'group' => 'settings'],
            ['name' => 'Manage Settings',      'slug' => 'manage_settings',      'group' => 'settings'],
        ];

        foreach ($permissions as $perm) {
            Permission::create($perm);
        }

        // ─── Assign permissions to roles ────────────────────

        // Admin gets everything except role/settings management
        $adminPermissions = Permission::whereNotIn('slug', ['manage_roles', 'manage_settings'])->pluck('id');
        $admin->permissions()->attach($adminPermissions);

        // Member gets view-only permissions
        $memberPermissions = Permission::whereIn('slug', [
            'view_members', 'view_meetings', 'view_payments',
            'view_content', 'view_events',
        ])->pluck('id');
        $member->permissions()->attach($memberPermissions);

        // Super admin gets all by default via hasPermission() check
    }
}
