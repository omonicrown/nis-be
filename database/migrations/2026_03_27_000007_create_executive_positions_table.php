<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');              // e.g. "Chairman", "Secretary", "Treasurer"
            $table->string('designation')->nullable(); // e.g. "MNIS", "FNIS"
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->integer('position_order')->default(0); // Display order
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_positions');
    }
};
