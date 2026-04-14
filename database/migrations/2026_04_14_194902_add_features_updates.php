<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Role-Permission pivot table (for Feature 3)
        if (!Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['role_id', 'permission_id']);
            });
        }

        // Add 'group' column to permissions if missing (for grouping by module)
        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'group')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('group')->nullable()->after('slug');
            });
        }

        // Make nis_membership_id unique for login (Feature 2)
        // Only add if the unique index doesn't already exist
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'nis_membership_id_unique_flag')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->unique('nis_membership_id');
                });
            } catch (\Exception $e) {
                // Index might already exist, skip
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');

        if (Schema::hasColumn('permissions', 'group')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('group');
            });
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['nis_membership_id']);
            });
        } catch (\Exception $e) {
        }
    }
};
