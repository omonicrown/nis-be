<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->year('due_year');
            $table->enum('type', ['membership_dues', 'event_registration', 'other'])->default('membership_dues');
            $table->decimal('amount_due', 12, 2);
            $table->integer('reminders_sent')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'due_year', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
    }
};
