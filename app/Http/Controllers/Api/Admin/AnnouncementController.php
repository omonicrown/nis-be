<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Announcement::with('creator')
            ->when($request->priority, fn($q, $p) => $q->where('priority', $p))
            ->when($request->visibility, fn($q, $v) => $q->where('visibility', $v))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy('created_at', 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 15));
    }

    public function store(AnnouncementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;

        $announcement = Announcement::create($validated);
        $announcement->load('creator');

        return $this->created(new AnnouncementResource($announcement), 'Announcement created.');
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $announcement->load('creator');
        return $this->success(new AnnouncementResource($announcement));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $announcement->update($request->validated());
        return $this->success(new AnnouncementResource($announcement), 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();
        return $this->success(null, 'Announcement deleted.');
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Announcement $announcement): JsonResponse
    {
        $announcement->update(['is_active' => !$announcement->is_active]);

        return $this->success(
            new AnnouncementResource($announcement),
            $announcement->is_active ? 'Announcement activated.' : 'Announcement deactivated.'
        );
    }
}
