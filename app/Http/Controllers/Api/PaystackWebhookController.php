<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentReminder;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    private PaystackService $paystack;

    public function __construct(PaystackService $paystack)
    {
        $this->paystack = $paystack;
    }

    /**
     * Handle Paystack webhook events.
     * URL: POST /api/webhooks/paystack
     *
     * Paystack sends events for charge.success, transfer.success, etc.
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate webhook signature
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (!$signature || !$this->paystack->validateWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook: Invalid signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info("Paystack webhook received: {$event}", ['reference' => $data['reference'] ?? null]);

        return match ($event) {
            'charge.success' => $this->handleChargeSuccess($data),
            'charge.failed'  => $this->handleChargeFailed($data),
            default          => response()->json(['message' => 'Event ignored']),
        };
    }

    /**
     * Handle successful charge.
     */
    private function handleChargeSuccess(array $data): JsonResponse
    {
        $reference = $data['reference'] ?? null;

        if (!$reference) {
            return response()->json(['message' => 'No reference'], 400);
        }

        $payment = Payment::where('paystack_reference', $reference)->first();

        if (!$payment) {
            Log::warning("Paystack webhook: Payment not found for reference {$reference}");
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Skip if already completed (idempotency)
        if ($payment->isCompleted()) {
            return response()->json(['message' => 'Already processed']);
        }

        $payment->update([
            'status'            => 'completed',
            'paystack_channel'  => $data['channel'] ?? null,
            'paystack_metadata' => $data,
            'paid_at'           => now(),
            'receipt_number'    => Payment::generateReceiptNumber(),
        ]);

        // Mark reminder as paid
        PaymentReminder::where('user_id', $payment->user_id)
            ->where('due_year', $payment->payment_year)
            ->where('type', $payment->type)
            ->where('is_paid', false)
            ->update(['is_paid' => true, 'payment_id' => $payment->id]);

        Log::info("Paystack webhook: Payment {$payment->id} completed via webhook");

        return response()->json(['message' => 'Payment processed']);
    }

    /**
     * Handle failed charge.
     */
    private function handleChargeFailed(array $data): JsonResponse
    {
        $reference = $data['reference'] ?? null;

        if (!$reference) {
            return response()->json(['message' => 'No reference'], 400);
        }

        $payment = Payment::where('paystack_reference', $reference)->first();

        if ($payment && $payment->isPending()) {
            $payment->update([
                'status'            => 'failed',
                'paystack_metadata' => $data,
            ]);
        }

        return response()->json(['message' => 'Failure recorded']);
    }
}
