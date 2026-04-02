<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // What the payment is for
            $table->enum('type', ['membership_dues', 'event_registration', 'donation', 'other'])->default('membership_dues');
            $table->string('description')->nullable();                // e.g. "2026 Annual Membership Dues — Fellow"
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NGN');

            // Payment method
            $table->enum('method', ['paystack', 'bank_transfer', 'cash', 'other'])->default('paystack');

            // Status
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');

            // Paystack fields
            $table->string('paystack_reference')->nullable()->unique();
            $table->string('paystack_access_code')->nullable();
            $table->string('paystack_authorization_url')->nullable();
            $table->string('paystack_channel')->nullable();           // card, bank, ussd, qr, etc.
            $table->json('paystack_metadata')->nullable();            // Full response from Paystack

            // Manual payment fields
            $table->string('manual_reference')->nullable();           // Bank transfer ref / receipt no.
            $table->text('manual_proof_url')->nullable();             // Cloudinary URL of proof screenshot
            $table->string('manual_proof_public_id')->nullable();     // Cloudinary public_id
            $table->text('manual_note')->nullable();                  // Member's note about the payment

            // Admin verification (for manual payments)
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('admin_note')->nullable();

            // Period tracking
            $table->year('payment_year')->nullable();                 // e.g. 2026
            $table->string('payment_period')->nullable();             // e.g. "2026", "Q1-2026", "Jan-2026"

            // Receipt
            $table->string('receipt_number')->nullable()->unique();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type', 'payment_year']);
            $table->index('status');
            $table->index('paystack_reference');
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
