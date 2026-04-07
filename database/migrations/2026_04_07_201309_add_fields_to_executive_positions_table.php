<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('executive_positions', function (Blueprint $table) {
            if (!Schema::hasColumn('executive_positions', 'name')) {
                $table->string('name')->nullable()->after('title');
            }
            if (!Schema::hasColumn('executive_positions', 'bio')) {
                $table->text('bio')->nullable()->after('designation');
            }
            if (!Schema::hasColumn('executive_positions', 'photo')) {
                $table->string('photo')->nullable()->after('bio');
            }
            if (!Schema::hasColumn('executive_positions', 'photo_public_id')) {
                $table->string('photo_public_id')->nullable()->after('photo');
            }
            if (!Schema::hasColumn('executive_positions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('position_order');
            }
            if (!Schema::hasColumn('executive_positions', 'start_date')) {
                $table->date('start_date')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('executive_positions', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('executive_positions', function (Blueprint $table) {
            //
        });
    }
};
