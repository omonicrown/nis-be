<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExecutivePosition;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutiveController extends Controller
{
    use ApiResponse;

    /**
     * List all executive positions.
     */
    public function index(): JsonResponse
    {
        $executives = ExecutivePosition::with('user:id,first_name,last_name,avatar')
            ->orderBy('position_order')
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'title'          => $e->title,
                'designation'    => $e->designation,
                'position_order' => $e->position_order,
                'is_active'      => (bool) $e->is_active,
                'start_date'     => $e->start_date,
                'end_date'       => $e->end_date,
                'photo'          => $e->photo,
                'bio'            => $e->bio,
                'user'           => $e->user ? [
                    'id'        => $e->user->id,
                    'full_name' => $e->user->full_name,
                    'avatar'    => $e->user->avatar,
                ] : null,
                'name'       => $e->name ?? $e->user?->full_name,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        return $this->success($executives);
    }

    /**
     * Create a new executive position.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'name'           => ['required', 'string', 'max:255'],
            'designation'    => ['nullable', 'string', 'max:20'],
            'bio'            => ['nullable', 'string', 'max:2000'],
            'position_order' => ['nullable', 'integer', 'min:1'],
            'user_id'        => ['nullable', 'exists:users,id'],
            'is_active'      => ['nullable', 'boolean'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date', 'after:start_date'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['position_order'] = $validated['position_order'] ?? (ExecutivePosition::max('position_order') + 1);

        $executive = ExecutivePosition::create($validated);

        return $this->created([
            'id'             => $executive->id,
            'title'          => $executive->title,
            'name'           => $executive->name,
            'designation'    => $executive->designation,
            'position_order' => $executive->position_order,
        ], 'Executive position created.');
    }

    /**
     * View a single executive position.
     */
    public function show(ExecutivePosition $executive): JsonResponse
    {
        $executive->load('user:id,first_name,last_name,avatar,email,phone');

        return $this->success([
            'id'             => $executive->id,
            'title'          => $executive->title,
            'name'           => $executive->name,
            'designation'    => $executive->designation,
            'bio'            => $executive->bio,
            'photo'          => $executive->photo,
            'position_order' => $executive->position_order,
            'is_active'      => (bool) $executive->is_active,
            'start_date'     => $executive->start_date,
            'end_date'       => $executive->end_date,
            'user'           => $executive->user ? [
                'id'        => $executive->user->id,
                'full_name' => $executive->user->full_name,
                'email'     => $executive->user->email,
                'phone'     => $executive->user->phone,
                'avatar'    => $executive->user->avatar,
            ] : null,
            'created_at' => $executive->created_at?->toIso8601String(),
            'updated_at' => $executive->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Update an executive position.
     */
    public function update(Request $request, ExecutivePosition $executive): JsonResponse
    {
        $validated = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'name'           => ['sometimes', 'string', 'max:255'],
            'designation'    => ['nullable', 'string', 'max:20'],
            'bio'            => ['nullable', 'string', 'max:2000'],
            'position_order' => ['nullable', 'integer', 'min:1'],
            'user_id'        => ['nullable', 'exists:users,id'],
            'is_active'      => ['nullable', 'boolean'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date'],
        ]);

        $executive->update($validated);

        return $this->success([
            'id'             => $executive->id,
            'title'          => $executive->title,
            'name'           => $executive->name,
            'designation'    => $executive->designation,
            'position_order' => $executive->position_order,
        ], 'Executive position updated.');
    }

    /**
     * Upload executive photo.
     */
    public function uploadPhoto(Request $request, ExecutivePosition $executive): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        try {
            $cloudinary = app(CloudinaryService::class);

            // Delete old photo if exists
            if ($executive->photo_public_id) {
                $cloudinary->delete($executive->photo_public_id);
            }

            $result = $cloudinary->uploadImage(
                $request->file('photo'),
                'nis-oyo/executives'
            );

            $executive->update([
                'photo'           => $result['secure_url'],
                'photo_public_id' => $result['public_id'],
            ]);

            return $this->success([
                'photo_url' => $result['secure_url'],
            ], 'Photo uploaded.');
        } catch (\Exception $e) {
            return $this->error('Failed to upload photo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an executive position.
     */
    public function destroy(ExecutivePosition $executive): JsonResponse
    {
        // Delete photo from Cloudinary if exists
        if ($executive->photo_public_id) {
            try {
                app(CloudinaryService::class)->delete($executive->photo_public_id);
            } catch (\Exception $e) {
                // Continue even if Cloudinary delete fails
            }
        }

        $executive->delete();

        return $this->success(null, 'Executive position deleted.');
    }

    /**
     * Reorder executive positions.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'positions'              => ['required', 'array'],
            'positions.*.id'         => ['required', 'exists:executive_positions,id'],
            'positions.*.position_order' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($validated['positions'] as $pos) {
            ExecutivePosition::where('id', $pos['id'])->update(['position_order' => $pos['position_order']]);
        }

        return $this->success(null, 'Positions reordered.');
    }
}
