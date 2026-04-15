<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot table: announcement can target multiple subgroups
        if (!Schema::hasTable('announcement_subgroup')) {
            Schema::create('announcement_subgroup', function (Blueprint $table) {
                $table->id();
                $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subgroup_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['announcement_id', 'subgroup_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_subgroup');
    }
};
