<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentReminder;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    private CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * List all payments with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['user.membershipCategory', 'verifiedBy'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type, fn($q, $t) => $q->byType($t))
            ->when($request->method, fn($q, $m) => $q->byMethod($m))
            ->when($request->year, fn($q, $y) => $q->byYear($y))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->when($request->boolean('manual_pending'), fn($q) => $q->manualPending())
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        $payments = $query->paginate($request->per_page ?? 20);

        return $this->paginated($payments->through(fn($p) => new PaymentResource($p)));
    }

    /**
     * View a single payment.
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['user.membershipCategory', 'verifiedBy']);

        return $this->success(new PaymentResource($payment));
    }

    /**
     * Verify/confirm a manual payment.
     */
    public function verifyManual(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->isManual()) {
            return $this->error('This is not a manual payment.');
        }

        if ($payment->isCompleted()) {
            return $this->error('Payment already verified.');
        }

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $payment->update([
            'status'         => 'completed',
            'verified_by'    => $request->user()->id,
            'verified_at'    => now(),
            'paid_at'        => now(),
            'admin_note'     => $request->admin_note,
            'receipt_number' => Payment::generateReceiptNumber(),
        ]);

        // Mark reminder as paid
        PaymentReminder::where('user_id', $payment->user_id)
            ->where('due_year', $payment->payment_year)
            ->where('type', $payment->type)
            ->where('is_paid', false)
            ->update(['is_paid' => true, 'payment_id' => $payment->id]);

        $payment->load(['user.membershipCategory', 'verifiedBy']);

        return $this->success(new PaymentResource($payment), 'Payment verified successfully.');
    }

    /**
     * Reject a manual payment.
     */
    public function rejectManual(Request $request, Payment $payment): JsonResponse
    {
        if (!$payment->isManual() || !$payment->isPending()) {
            return $this->error('Can only reject pending manual payments.');
        }

        $request->validate([
            'admin_note' => ['required', 'string', 'max:500'],
        ]);

        $payment->update([
            'status'      => 'failed',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'admin_note'  => $request->admin_note,
        ]);

        return $this->success(null, 'Payment rejected.');
    }

    /**
     * Admin creates a payment on behalf of a member (cash received at office).
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'        => ['required', 'exists:users,id'],
            'type'           => ['required', 'in:membership_dues,event_registration,donation,other'],
            'amount'         => ['required', 'numeric', 'min:100'],
            'method'         => ['required', 'in:bank_transfer,cash,other'],
            'description'    => ['nullable', 'string', 'max:500'],
            'payment_year'   => ['nullable', 'integer'],
            'manual_reference' => ['nullable', 'string', 'max:255'],
            'admin_note'     => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        $payment = Payment::create([
            'user_id'          => $user->id,
            'type'             => $validated['type'],
            'description'      => $validated['description'] ?? "{$validated['payment_year']} Dues — {$user->membershipCategory?->name}",
            'amount'           => $validated['amount'],
            'method'           => $validated['method'],
            'status'           => 'completed',
            'manual_reference' => $validated['manual_reference'] ?? null,
            'admin_note'       => $validated['admin_note'] ?? null,
            'payment_year'     => $validated['payment_year'] ?? date('Y'),
            'payment_period'   => $validated['payment_year'] ?? date('Y'),
            'verified_by'      => $request->user()->id,
            'verified_at'      => now(),
            'paid_at'          => now(),
            'receipt_number'   => Payment::generateReceiptNumber(),
        ]);

        // Mark reminder as paid
        PaymentReminder::where('user_id', $user->id)
            ->where('due_year', $payment->payment_year)
            ->where('type', $payment->type)
            ->where('is_paid', false)
            ->update(['is_paid' => true, 'payment_id' => $payment->id]);

        $payment->load(['user.membershipCategory', 'verifiedBy']);

        return $this->created(new PaymentResource($payment), 'Payment recorded.');
    }

    /**
     * Get count of manual payments awaiting verification.
     */
    public function pendingManualCount(): JsonResponse
    {
        $count = Payment::manualPending()->count();

        return $this->success(['pending_count' => $count]);
    }

    /**
     * Payment summary/stats for dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        $yearPayments = Payment::byYear($year);

        $stats = [
            'year'                  => (int) $year,
            'total_revenue'         => (clone $yearPayments)->completed()->sum('amount'),
            'total_pending'         => (clone $yearPayments)->pending()->sum('amount'),
            'total_transactions'    => (clone $yearPayments)->count(),
            'completed_count'       => (clone $yearPayments)->completed()->count(),
            'pending_count'         => (clone $yearPayments)->pending()->count(),
            'failed_count'          => (clone $yearPayments)->failed()->count(),
            'manual_pending_count'  => (clone $yearPayments)->manualPending()->count(),
            'by_method' => [
                'paystack'      => (clone $yearPayments)->completed()->byMethod('paystack')->sum('amount'),
                'bank_transfer' => (clone $yearPayments)->completed()->byMethod('bank_transfer')->sum('amount'),
                'cash'          => (clone $yearPayments)->completed()->byMethod('cash')->sum('amount'),
            ],
            'by_type' => [
                'membership_dues'    => (clone $yearPayments)->completed()->byType('membership_dues')->sum('amount'),
                'event_registration' => (clone $yearPayments)->completed()->byType('event_registration')->sum('amount'),
                'donation'           => (clone $yearPayments)->completed()->byType('donation')->sum('amount'),
                'other'              => (clone $yearPayments)->completed()->byType('other')->sum('amount'),
            ],
            'monthly_revenue' => $this->getMonthlyRevenue($year),
        ];

        return $this->success($stats);
    }

    /**
     * Members with outstanding dues.
     */
    public function outstandingDues(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        $members = User::active()
            ->with('membershipCategory')
            ->whereNotNull('membership_category_id')
            ->get()
            ->map(function ($member) use ($year) {
                $fee = $member->membershipCategory->annual_fee ?? 0;
                $paid = Payment::where('user_id', $member->id)
                    ->byType('membership_dues')
                    ->byYear($year)
                    ->completed()
                    ->sum('amount');

                $outstanding = max(0, $fee - $paid);

                return [
                    'user_id'             => $member->id,
                    'full_name'           => $member->full_name,
                    'email'               => $member->email,
                    'membership_category' => $member->membershipCategory->name,
                    'annual_fee'          => $fee,
                    'amount_paid'         => $paid,
                    'outstanding'         => $outstanding,
                    'is_fully_paid'       => $outstanding <= 0,
                ];
            })
            ->filter(fn($m) => $m['outstanding'] > 0)
            ->sortByDesc('outstanding')
            ->values();

        return $this->success([
            'year'          => (int) $year,
            'total_outstanding' => $members->sum('outstanding'),
            'members_count'     => $members->count(),
            'members'           => $members,
        ]);
    }

    /**
     * Generate due reminders for all active members for a given year.
     * Can be called via cron.
     */
    public function generateReminders(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));
        $created = 0;

        $members = User::active()
            ->with('membershipCategory')
            ->whereNotNull('membership_category_id')
            ->get();

        foreach ($members as $member) {
            $fee = $member->membershipCategory->annual_fee ?? 0;
            if ($fee <= 0) continue;

            // Check if already paid
            $paid = Payment::where('user_id', $member->id)
                ->byType('membership_dues')
                ->byYear($year)
                ->completed()
                ->sum('amount');

            if ($paid >= $fee) continue;

            // Create or update reminder
            PaymentReminder::updateOrCreate(
                [
                    'user_id'  => $member->id,
                    'due_year' => $year,
                    'type'     => 'membership_dues',
                ],
                [
                    'amount_due' => $fee,
                    'is_paid'    => false,
                ]
            );
            $created++;
        }

        return $this->success(
            ['reminders_created' => $created],
            "{$created} reminder(s) created/updated for {$year}."
        );
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function getMonthlyRevenue(int $year): array
    {
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month'   => $m,
                'label'   => date('M', mktime(0, 0, 0, $m, 1)),
                'revenue' => Payment::completed()
                    ->byYear($year)
                    ->whereMonth('paid_at', $m)
                    ->sum('amount'),
            ];
        }
        return $monthly;
    }
}
