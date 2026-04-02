<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Addresses
            $table->text('office_address')->nullable();
            $table->text('residential_address')->nullable();

            // Personal details
            $table->date('date_of_birth')->nullable();
            $table->text('bio')->nullable();

            // Professional details
            $table->string('specialization')->nullable();
            $table->string('firm_name')->nullable();
            $table->year('year_of_registration')->nullable();

            // Privacy controls
            $table->boolean('show_email')->default(true);
            $table->boolean('show_phone')->default(true);
            $table->boolean('show_office_address')->default(true);
            $table->boolean('show_residential_address')->default(false);
            $table->boolean('show_in_directory')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
