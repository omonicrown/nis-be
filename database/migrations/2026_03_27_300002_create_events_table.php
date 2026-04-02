<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->longText('details')->nullable();              // Full event details / rich text
            $table->string('banner_url')->nullable();              // Cloudinary
            $table->string('banner_public_id')->nullable();

            // Date & time
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Location
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_virtual')->default(false);
            $table->string('virtual_link')->nullable();            // Zoom/Meet link

            // Type & status
            $table->enum('type', ['seminar', 'workshop', 'conference', 'agm', 'social', 'training', 'other'])->default('seminar');
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled'])->default('upcoming');

            // Registration
            $table->boolean('requires_registration')->default(false);
            $table->integer('max_attendees')->nullable();
            $table->decimal('registration_fee', 10, 2)->nullable();
            $table->timestamp('registration_deadline')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['start_date', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
