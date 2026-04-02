<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');                                    // e.g. "Monthly General Meeting — March 2026"
            $table->text('description')->nullable();
            $table->date('meeting_date');
            $table->time('start_time')->nullable();                     // e.g. 15:30
            $table->time('end_time')->nullable();
            $table->string('venue')->nullable();                        // e.g. "NIS Plaza, Ikolaba"
            $table->enum('type', ['general', 'special', 'emergency', 'executive'])->default('general');
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');

            // Agenda
            $table->longText('agenda')->nullable();                     // JSON or rich text

            // Minutes
            $table->longText('minutes_text')->nullable();               // Text content of minutes
            $table->string('minutes_file_url')->nullable();             // Cloudinary URL for uploaded PDF
            $table->string('minutes_file_public_id')->nullable();       // Cloudinary public_id for deletion

            // QR Code for attendance
            $table->string('qr_code')->nullable();                      // Unique code for QR check-in
            $table->string('qr_code_url')->nullable();                  // Generated QR image URL

            // Meta
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('meeting_date');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
