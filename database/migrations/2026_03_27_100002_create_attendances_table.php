<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Status matches NIS meeting format: present, absent, apology
            $table->enum('status', ['present', 'absent', 'apology'])->default('absent');

            // How they checked in
            $table->enum('check_in_method', ['manual', 'qr_code', 'admin'])->nullable();
            $table->timestamp('checked_in_at')->nullable();

            // Who marked them (for admin-entered attendance)
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('note')->nullable();          // e.g. apology reason
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']); // One record per member per meeting
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
