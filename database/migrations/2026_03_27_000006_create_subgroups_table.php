<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subgroups', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // e.g. "APPSN", "YSN", "WIS", "NASGL"
            $table->string('slug')->unique();
            $table->string('full_name')->nullable(); // e.g. "Young Surveyors Network"
            $table->text('description')->nullable();
            $table->string('chairperson')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('member_subgroup', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subgroup_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'subgroup_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subgroup');
        Schema::dropIfExists('subgroups');
    }
};
