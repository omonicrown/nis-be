<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\CloudinaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use ApiResponse;

    private CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Event::with(['creator', 'registrations'])
            ->when($request->type, fn($q, $t) => $q->byType($t))
            ->when($request->status, fn($q, $s) => $q->byStatus($s))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->when($request->boolean('upcoming'), fn($q) => $q->upcoming())
            ->orderBy($request->sort_by ?? 'start_date', $request->sort_dir ?? 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 15));
    }

    public function store(EventRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $event = Event::create($validated);
        $event->load(['creator', 'registrations']);

        return $this->created(new EventResource($event), 'Event created.');
    }

    public function show(Event $event): JsonResponse
    {
        $event->load(['creator', 'registrations.user.membershipCategory']);
        return $this->success(new EventResource($event));
    }

    public function update(EventRequest $request, Event $event): JsonResponse
    {
        $event->update($request->validated());
        $event->load(['creator', 'registrations']);
        return $this->success(new EventResource($event), 'Event updated.');
    }

    public function destroy(Event $event): JsonResponse
    {
        if ($event->banner_public_id) {
            $this->cloudinary->delete($event->banner_public_id);
        }
        $event->delete();
        return $this->success(null, 'Event deleted.');
    }

    public function uploadBanner(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'banner' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($event->banner_public_id) {
            $this->cloudinary->delete($event->banner_public_id);
        }

        $result = $this->cloudinary->uploadImage($request->file('banner'), 'events');

        $event->update([
            'banner_url'       => $result['secure_url'],
            'banner_public_id' => $result['public_id'],
        ]);

        return $this->success(['banner_url' => $result['secure_url']], 'Banner uploaded.');
    }

    public function updateStatus(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:upcoming,ongoing,completed,cancelled'],
        ]);

        $event->update(['status' => $request->status]);
        return $this->success(new EventResource($event), 'Status updated.');
    }

    /**
     * List registrations for an event.
     */
    public function registrations(Event $event): JsonResponse
    {
        $registrations = $event->registrations()
            ->with(['user.membershipCategory', 'payment'])
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'status'     => $r->status,
                'member'     => [
                    'id'                => $r->user->id,
                    'full_name'         => $r->user->full_name,
                    'email'             => $r->user->email,
                    'membership_category' => $r->user->membershipCategory?->name,
                ],
                'payment'    => $r->payment ? [
                    'id'     => $r->payment->id,
                    'amount' => $r->payment->amount,
                    'status' => $r->payment->status,
                ] : null,
                'registered_at' => $r->created_at->toIso8601String(),
            ]);

        return $this->success([
            'event'          => new EventResource($event),
            'registrations'  => $registrations,
            'total'          => $registrations->count(),
            'attended_count' => $registrations->where('status', 'attended')->count(),
        ]);
    }
}
