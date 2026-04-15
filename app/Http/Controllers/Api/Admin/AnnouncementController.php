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
        $query = Announcement::with(['creator', 'subgroups:id,name'])
            ->when($request->priority, fn($q, $p) => $q->where('priority', $p))
            ->when($request->visibility, fn($q, $v) => $q->where('visibility', $v))
            ->when($request->subgroup_id, fn($q, $s) => $q->whereHas('subgroups', fn($sq) => $sq->where('subgroup_id', $s)))
            ->when($request->boolean('active_only'), fn($q) => $q->active())
            ->orderBy('created_at', 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 15));
    }

    public function store(AnnouncementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $subgroupIds = $validated['subgroup_ids'] ?? null;
        unset($validated['subgroup_ids']);

        $validated['created_by'] = $request->user()->id;

        $announcement = Announcement::create($validated);

        if ($subgroupIds) {
            $announcement->subgroups()->sync($subgroupIds);
        }

        $announcement->load(['creator', 'subgroups:id,name']);

        return $this->created(new AnnouncementResource($announcement), 'Announcement created.');
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $announcement->load(['creator', 'subgroups:id,name']);
        return $this->success(new AnnouncementResource($announcement));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $validated = $request->validated();
        $subgroupIds = $validated['subgroup_ids'] ?? null;
        unset($validated['subgroup_ids']);

        $announcement->update($validated);

        if ($subgroupIds !== null) {
            $announcement->subgroups()->sync($subgroupIds);
        }

        $announcement->load(['creator', 'subgroups:id,name']);

        return $this->success(new AnnouncementResource($announcement), 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->subgroups()->detach();
        $announcement->delete();
        return $this->success(null, 'Announcement deleted.');
    }

    public function toggleActive(Announcement $announcement): JsonResponse
    {
        $announcement->update(['is_active' => !$announcement->is_active]);

        return $this->success(
            new AnnouncementResource($announcement),
            $announcement->is_active ? 'Announcement activated.' : 'Announcement deactivated.'
        );
    }
}
