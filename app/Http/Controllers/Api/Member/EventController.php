<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use ApiResponse;

    /**
     * List events.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('registrations')
            ->when($request->type, fn($q, $t) => $q->byType($t))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->when($request->boolean('upcoming'), fn($q) => $q->upcoming())
            ->whereIn('status', ['upcoming', 'ongoing', 'completed'])
            ->orderBy('start_date', 'desc');

        return $this->paginated(
            $query->paginate($request->per_page ?? 15)->through(fn($e) => new EventResource($e))
        );
    }

    /**
     * View single event.
     */
    public function show(Event $event): JsonResponse
    {
        $event->load('registrations');
        return $this->success(new EventResource($event));
    }

    /**
     * Register / RSVP for an event.
     */
    public function register(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        if (!$event->requires_registration) {
            return $this->error('This event does not require registration.');
        }

        if (!$event->isRegistrationOpen()) {
            return $this->error('Registration is closed for this event.');
        }

        // Check if already registered
        $existing = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->status !== 'cancelled') {
            return $this->error('You are already registered for this event.');
        }

        $registration = EventRegistration::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id'  => $user->id,
            ],
            [
                'status' => 'registered',
                'note'   => $request->note ?? null,
            ]
        );

        return $this->success([
            'registration_id' => $registration->id,
            'status'          => $registration->status,
            'event'           => $event->title,
            'has_fee'         => $event->registration_fee > 0,
            'fee_amount'      => $event->registration_fee,
        ], 'Registered successfully!' . ($event->registration_fee > 0
            ? ' Please proceed to pay the registration fee.'
            : ''));
    }

    /**
     * Cancel registration.
     */
    public function cancelRegistration(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $registration = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$registration || $registration->status === 'cancelled') {
            return $this->error('No active registration found.');
        }

        if ($event->status === 'completed') {
            return $this->error('Cannot cancel registration for a completed event.');
        }

        $registration->update(['status' => 'cancelled']);

        return $this->success(null, 'Registration cancelled.');
    }

    /**
     * My registered events.
     */
    public function myEvents(Request $request): JsonResponse
    {
        $user = $request->user();

        $registrations = EventRegistration::with(['event', 'payment'])
            ->where('user_id', $user->id)
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        $transformed = $registrations->through(fn($r) => [
            'registration_id' => $r->id,
            'status'          => $r->status,
            'event'           => [
                'id'         => $r->event->id,
                'title'      => $r->event->title,
                'start_date' => $r->event->start_date->format('Y-m-d'),
                'venue'      => $r->event->venue,
                'type'       => $r->event->type,
                'status'     => $r->event->status,
            ],
            'payment' => $r->payment ? [
                'amount' => $r->payment->amount,
                'status' => $r->payment->status,
            ] : null,
            'registered_at' => $r->created_at->toIso8601String(),
        ]);

        return $this->paginated($transformed);
    }
}
