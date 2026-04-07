<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\ResourceResource;
use App\Models\Announcement;
use App\Models\Post;
use App\Models\Resource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    use ApiResponse;

    // ─── Blog / News ────────────────────────────────────────

    /**
     * List published posts (public + members_only).
     */
    public function posts(Request $request): JsonResponse
    {
        $query = Post::with('author')
            ->published()
            ->when($request->category, fn($q, $c) => $q->byCategory($c))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        return $this->paginated(
            $query->paginate($request->per_page ?? 15)->through(fn($p) => new PostResource($p))
        );
    }

    /**
     * View single post by slug.
     */
    public function showPost(string $slug): JsonResponse
    {
        $post = Post::with('author')
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->success(new PostResource($post));
    }

    // ─── Announcements ──────────────────────────────────────

    /**
     * Active announcements for members.
     */
    public function announcements(Request $request): JsonResponse
    {
        $announcements = Announcement::with('creator')
            ->active()
            ->forMembers()
            ->when($request->priority, fn($q, $p) => $q->where('priority', $p))
            ->orderByRaw("array_position(ARRAY['urgent','high','normal','low'], priority)")
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 10);

        return $this->paginated(
            $announcements->through(fn($a) => new AnnouncementResource($a))
        );
    }

    // ─── Resource Library ───────────────────────────────────

    /**
     * Browse resources (public + members_only).
     */
    public function resources(Request $request): JsonResponse
    {
        $query = Resource::with('uploader')
            ->when($request->category, fn($q, $c) => $q->byCategory($c))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->orderBy('title');

        return $this->paginated(
            $query->paginate($request->per_page ?? 20)->through(fn($r) => new ResourceResource($r))
        );
    }

    /**
     * Get resource categories with counts.
     */
    public function resourceCategories(): JsonResponse
    {
        $categories = Resource::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn($c) => [
                'category' => $c->category,
                'label'    => ucfirst($c->category),
                'count'    => $c->count,
            ]);

        return $this->success($categories);
    }

    /**
     * Download a resource (increments counter).
     */
    public function downloadResource(Resource $resource): JsonResponse
    {
        $resource->incrementDownloads();

        return $this->success([
            'file_url'  => $resource->file_url,
            'file_name' => $resource->file_name,
        ]);
    }
}
