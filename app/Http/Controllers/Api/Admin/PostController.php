<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\CloudinaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use ApiResponse;

    private CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Post::with('author')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->category, fn($q, $c) => $q->byCategory($c))
            ->when($request->visibility, fn($q, $v) => $q->where('visibility', $v))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 15));
    }

    public function store(PostRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['author_id'] = $request->user()->id;

        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $post = Post::create($validated);

        return $this->created(new PostResource($post), 'Post created.');
    }

    public function show(Post $post): JsonResponse
    {
        $post->load('author');
        return $this->success(new PostResource($post));
    }

    public function update(PostRequest $request, Post $post): JsonResponse
    {
        $validated = $request->validated();

        // Auto-set published_at when publishing
        if (($validated['status'] ?? null) === 'published' && !$post->published_at) {
            $validated['published_at'] = now();
        }

        $post->update($validated);
        return $this->success(new PostResource($post), 'Post updated.');
    }

    public function destroy(Post $post): JsonResponse
    {
        if ($post->featured_image_public_id) {
            $this->cloudinary->delete($post->featured_image_public_id);
        }
        $post->delete();
        return $this->success(null, 'Post deleted.');
    }

    public function uploadImage(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($post->featured_image_public_id) {
            $this->cloudinary->delete($post->featured_image_public_id);
        }

        $result = $this->cloudinary->uploadImage($request->file('image'), 'blog');

        $post->update([
            'featured_image_url'       => $result['secure_url'],
            'featured_image_public_id' => $result['public_id'],
        ]);

        return $this->success(['image_url' => $result['secure_url']], 'Image uploaded.');
    }
}
