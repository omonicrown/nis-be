<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\MemberProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        // Super Admin
        $superAdmin = User::create([
            'first_name'             => 'System',
            'last_name'              => 'Administrator',
            'email'                  => 'admin@nisoyostate.org',
            'phone'                  => '07025547335',
            'gender'                 => 'male',
            'role_id'                => $superAdminRole->id,
            'membership_category_id' => null,
            'status'                 => UserStatus::ACTIVE,
            'email_verified_at'      => now(),
            'approved_at'            => now(),
            'password'               => Hash::make('NIS@dmin2026!'),
            'profile_completed'      => true,
        ]);

        MemberProfile::create([
            'user_id'        => $superAdmin->id,
            'office_address' => '1A Fadaiya Oyeniyi Street, Off Custom / Federal Secretariat Road, Ikolaba Ibadan',
        ]);

        // Admin 2 (Dummy — replace with real data later)
        $admin2 = User::create([
            'first_name'             => 'Admin',
            'last_name'              => 'Officer',
            'email'                  => 'admin2@nisoyostate.org',
            'phone'                  => '08000000001',
            'gender'                 => 'male',
            'role_id'                => $adminRole->id,
            'membership_category_id' => null,
            'status'                 => UserStatus::ACTIVE,
            'email_verified_at'      => now(),
            'approved_at'            => now(),
            'password'               => Hash::make('NIS@dmin2026!'),
            'profile_completed'      => true,
        ]);

        MemberProfile::create([
            'user_id'        => $admin2->id,
            'office_address' => '1A Fadaiya Oyeniyi Street, Off Custom / Federal Secretariat Road, Ikolaba Ibadan',
        ]);

        // Admin 3 (Dummy — replace with real data later)
        $admin3 = User::create([
            'first_name'             => 'Admin',
            'last_name'              => 'Three',
            'email'                  => 'admin3@nisoyostate.org',
            'phone'                  => '08000000002',
            'gender'                 => 'female',
            'role_id'                => $adminRole->id,
            'membership_category_id' => null,
            'status'                 => UserStatus::ACTIVE,
            'email_verified_at'      => now(),
            'approved_at'            => now(),
            'password'               => Hash::make('NIS@dmin2026!'),
            'profile_completed'      => true,
        ]);

        MemberProfile::create([
            'user_id'        => $admin3->id,
            'office_address' => '1A Fadaiya Oyeniyi Street, Off Custom / Federal Secretariat Road, Ikolaba Ibadan',
        ]);
    }
}
