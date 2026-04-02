<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\InitializePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentReminder;
use App\Services\CloudinaryService;
use App\Services\PaystackService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    private PaystackService $paystack;
    private CloudinaryService $cloudinary;

    public function __construct(PaystackService $paystack, CloudinaryService $cloudinary)
    {
        $this->paystack = $paystack;
        $this->cloudinary = $cloudinary;
    }

    /**
     * Initialize a payment (Paystack or Manual).
     */
    public function initialize(InitializePaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $method = $validated['method'];

        // Create payment record
        $payment = Payment::create([
            'user_id'          => $user->id,
            'type'             => $validated['type'],
            'description'      => $validated['description'] ?? $this->generateDescription($validated['type'], $user),
            'amount'           => $validated['amount'],
            'method'           => $method,
            'status'           => 'pending',
            'payment_year'     => $validated['payment_year'] ?? date('Y'),
            'payment_period'   => $validated['payment_period'] ?? date('Y'),
            'manual_reference' => $validated['manual_reference'] ?? null,
            'manual_note'      => $validated['manual_note'] ?? null,
        ]);

        // Handle Paystack initialization
        if ($method === 'paystack') {
            try {
                $result = $this->paystack->initialize([
                    'email'        => $user->email,
                    'amount'       => $validated['amount'],
                    'callback_url' => $validated['callback_url'] ?? null,
                    'metadata'     => [
                        'payment_id' => $payment->id,
                        'user_id'    => $user->id,
                        'type'       => $validated['type'],
                    ],
                ]);

                $payment->update([
                    'paystack_reference'         => $result['reference'],
                    'paystack_access_code'       => $result['access_code'],
                    'paystack_authorization_url' => $result['authorization_url'],
                ]);

                return $this->success([
                    'payment'           => new PaymentResource($payment),
                    'authorization_url' => $result['authorization_url'],
                    'reference'         => $result['reference'],
                    'access_code'       => $result['access_code'],
                ], 'Payment initialized. Redirect to Paystack to complete.');

            } catch (\Exception $e) {
                $payment->update(['status' => 'failed']);

                return $this->error('Failed to initialize Paystack: ' . $e->getMessage(), 500);
            }
        }

        // Manual payment — just return the pending record
        return $this->created([
            'payment' => new PaymentResource($payment),
        ], 'Payment recorded. ' . ($method === 'bank_transfer'
            ? 'Awaiting admin verification of your bank transfer.'
            : 'Awaiting admin confirmation.'));
    }

    /**
     * Verify Paystack payment after callback.
     * Frontend calls this after redirect from Paystack.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => ['required', 'string'],
        ]);

        $payment = Payment::where('paystack_reference', $request->reference)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return $this->notFound('Payment not found.');
        }

        if ($payment->isCompleted()) {
            return $this->success(new PaymentResource($payment), 'Payment already verified.');
        }

        try {
            $result = $this->paystack->verify($request->reference);

            if ($result['status'] === 'success') {
                $payment->update([
                    'status'             => 'completed',
                    'paystack_channel'   => $result['channel'] ?? null,
                    'paystack_metadata'  => $result,
                    'paid_at'            => now(),
                    'receipt_number'     => Payment::generateReceiptNumber(),
                ]);

                // Mark reminder as paid
                $this->markReminderPaid($payment);

                return $this->success(new PaymentResource($payment->fresh()), 'Payment successful!');
            }

            $payment->update([
                'status'            => 'failed',
                'paystack_metadata' => $result,
            ]);

            return $this->error('Payment was not successful. Status: ' . ($result['status'] ?? 'unknown'));

        } catch (\Exception $e) {
            return $this->error('Verification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload proof of payment (screenshot/receipt) for manual payments.
     */
    public function uploadProof(Request $request, Payment $payment): JsonResponse
    {
        // Ensure user owns this payment
        if ($payment->user_id !== $request->user()->id) {
            return $this->forbidden('You can only upload proof for your own payments.');
        }

        if (!$payment->isManual()) {
            return $this->error('Proof upload is only for manual payments.');
        }

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // 5MB
        ]);

        // Delete old proof
        if ($payment->manual_proof_public_id) {
            $this->cloudinary->delete($payment->manual_proof_public_id);
        }

        $result = $this->cloudinary->upload($request->file('proof'), 'payment-proofs');

        $payment->update([
            'manual_proof_url'       => $result['secure_url'],
            'manual_proof_public_id' => $result['public_id'],
        ]);

        return $this->success([
            'proof_url' => $result['secure_url'],
        ], 'Payment proof uploaded.');
    }

    /**
     * My payment history.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Payment::where('user_id', $user->id)
            ->when($request->type, fn($q, $t) => $q->byType($t))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->year, fn($q, $y) => $q->byYear($y))
            ->orderBy('created_at', 'desc');

        $payments = $query->paginate($request->per_page ?? 15);

        // Summary
        $allPayments = Payment::where('user_id', $user->id);
        $summary = [
            'total_paid'    => (clone $allPayments)->completed()->sum('amount'),
            'pending'       => (clone $allPayments)->pending()->sum('amount'),
            'pending_count' => (clone $allPayments)->pending()->count(),
            'this_year'     => (clone $allPayments)->completed()->byYear(date('Y'))->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data'    => PaymentResource::collection($payments->items()),
            'summary' => $summary,
            'meta'    => [
                'current_page' => $payments->currentPage(),
                'last_page'    => $payments->lastPage(),
                'per_page'     => $payments->perPage(),
                'total'        => $payments->total(),
            ],
        ]);
    }

    /**
     * View a single payment.
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->user_id !== $request->user()->id) {
            return $this->forbidden('You can only view your own payments.');
        }

        return $this->success(new PaymentResource($payment));
    }

    /**
     * Get dues status for the current year.
     */
    public function duesStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('membershipCategory');
        $year = $request->get('year', date('Y'));

        $annualFee = $user->membershipCategory?->annual_fee ?? 0;

        $paidThisYear = Payment::where('user_id', $user->id)
            ->byType('membership_dues')
            ->byYear($year)
            ->completed()
            ->sum('amount');

        $pendingThisYear = Payment::where('user_id', $user->id)
            ->byType('membership_dues')
            ->byYear($year)
            ->pending()
            ->sum('amount');

        return $this->success([
            'year'               => (int) $year,
            'annual_fee'         => $annualFee,
            'amount_paid'        => $paidThisYear,
            'amount_pending'     => $pendingThisYear,
            'amount_outstanding' => max(0, $annualFee - $paidThisYear),
            'is_fully_paid'      => $paidThisYear >= $annualFee,
            'membership_category' => $user->membershipCategory?->name,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function generateDescription(string $type, $user): string
    {
        $year = date('Y');
        $category = $user->membershipCategory?->name ?? 'Member';

        return match ($type) {
            'membership_dues'    => "{$year} Annual Membership Dues — {$category}",
            'event_registration' => "{$year} Event Registration",
            'donation'           => "Donation to NIS Oyo State Branch",
            default              => "Payment to NIS Oyo State Branch",
        };
    }

    private function markReminderPaid(Payment $payment): void
    {
        PaymentReminder::where('user_id', $payment->user_id)
            ->where('due_year', $payment->payment_year)
            ->where('type', $payment->type)
            ->where('is_paid', false)
            ->update([
                'is_paid'    => true,
                'payment_id' => $payment->id,
            ]);
    }
}
