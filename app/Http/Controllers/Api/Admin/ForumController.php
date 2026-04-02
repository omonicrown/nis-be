<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ForumController extends Controller
{
    use ApiResponse;

    // ─── Categories ─────────────────────────────────────────

    public function categories(): JsonResponse
    {
        $categories = ForumCategory::withCount('topics')
            ->orderBy('sort_order')
            ->get();

        return $this->success($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = ForumCategory::create($validated);

        return $this->created($category, 'Category created.');
    }

    public function updateCategory(Request $request, ForumCategory $forumCategory): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order'  => ['nullable', 'integer'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $forumCategory->update($validated);

        return $this->success($forumCategory, 'Category updated.');
    }

    public function deleteCategory(ForumCategory $forumCategory): JsonResponse
    {
        $forumCategory->delete();
        return $this->success(null, 'Category deleted.');
    }

    // ─── Topic Moderation ───────────────────────────────────

    public function pinTopic(ForumTopic $forumTopic): JsonResponse
    {
        $forumTopic->update(['is_pinned' => !$forumTopic->is_pinned]);

        return $this->success(null, $forumTopic->is_pinned ? 'Topic pinned.' : 'Topic unpinned.');
    }

    public function lockTopic(ForumTopic $forumTopic): JsonResponse
    {
        $forumTopic->update(['is_locked' => !$forumTopic->is_locked]);

        return $this->success(null, $forumTopic->is_locked ? 'Topic locked.' : 'Topic unlocked.');
    }

    public function deleteTopic(ForumTopic $forumTopic): JsonResponse
    {
        $forumTopic->delete();
        return $this->success(null, 'Topic deleted.');
    }

    public function deleteReply(ForumReply $forumReply): JsonResponse
    {
        $forumReply->delete();
        return $this->success(null, 'Reply deleted.');
    }

    /**
     * Forum stats for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        return $this->success([
            'total_categories' => ForumCategory::count(),
            'total_topics'     => ForumTopic::count(),
            'total_replies'    => ForumReply::count(),
            'topics_this_month' => ForumTopic::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
            'replies_this_month' => ForumReply::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
        ]);
    }
}
