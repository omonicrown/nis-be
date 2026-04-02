<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // e.g. "Fellow", "Member", "Associate", "Probationer", "Student"
            $table->string('slug')->unique();
            $table->string('designation')->nullable(); // e.g. "FNIS", "MNIS", "ANIS"
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();  // JSON or text describing requirements
            $table->decimal('annual_fee', 10, 2);      // Fee amount
            $table->integer('rank')->default(0);        // Hierarchy: Fellow=5, Member=4, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_categories');
    }
};
