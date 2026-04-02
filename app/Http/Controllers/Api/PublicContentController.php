<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\ResourceResource;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Post;
use App\Models\Resource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicContentController extends Controller
{
    use ApiResponse;

    /**
     * Public blog posts (visibility = public only).
     */
    public function posts(Request $request): JsonResponse
    {
        $query = Post::with('author')
            ->published()
            ->public()
            ->when($request->category, fn($q, $c) => $q->byCategory($c))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        return $this->paginated(
            $query->paginate($request->per_page ?? 15)->through(fn($p) => new PostResource($p))
        );
    }

    /**
     * Single public post by slug.
     */
    public function showPost(string $slug): JsonResponse
    {
        $post = Post::with('author')
            ->published()
            ->public()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->success(new PostResource($post));
    }

    /**
     * Public events.
     */
    public function events(Request $request): JsonResponse
    {
        $query = Event::whereIn('status', ['upcoming', 'ongoing', 'completed'])
            ->when($request->type, fn($q, $t) => $q->byType($t))
            ->when($request->boolean('upcoming'), fn($q) => $q->upcoming())
            ->orderBy('start_date', 'desc');

        return $this->paginated(
            $query->paginate($request->per_page ?? 15)->through(fn($e) => new EventResource($e))
        );
    }

    /**
     * Single event.
     */
    public function showEvent(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        return $this->success(new EventResource($event));
    }

    /**
     * Public announcements.
     */
    public function announcements(): JsonResponse
    {
        $announcements = Announcement::active()
            ->forPublic()
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return $this->success(AnnouncementResource::collection($announcements));
    }

    /**
     * Public resources (visibility = public only).
     */
    public function resources(Request $request): JsonResponse
    {
        $query = Resource::public()
            ->when($request->category, fn($q, $c) => $q->byCategory($c))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->orderBy('title');

        return $this->paginated(
            $query->paginate($request->per_page ?? 20)->through(fn($r) => new ResourceResource($r))
        );
    }
}
