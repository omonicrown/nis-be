<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    use ApiResponse;

    /**
     * List forum categories with topic counts.
     */
    public function categories(): JsonResponse
    {
        $categories = ForumCategory::active()
            ->withCount('topics')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'slug'         => $c->slug,
                'description'  => $c->description,
                'topics_count' => $c->topics_count,
            ]);

        return $this->success($categories);
    }

    /**
     * List topics in a category.
     */
    public function topics(Request $request, ForumCategory $forumCategory): JsonResponse
    {
        $query = $forumCategory->topics()
            ->with(['author.membershipCategory'])
            ->withCount('replies')
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at');

        $topics = $query->paginate($request->per_page ?? 20);

        $transformed = $topics->through(fn($t) => [
            'id'            => $t->id,
            'title'         => $t->title,
            'slug'          => $t->slug,
            'body_preview'  => \Illuminate\Support\Str::limit(strip_tags($t->body), 150),
            'is_pinned'     => $t->is_pinned,
            'is_locked'     => $t->is_locked,
            'views_count'   => $t->views_count,
            'replies_count' => $t->replies_count,
            'last_reply_at' => $t->last_reply_at?->toIso8601String(),
            'author'        => [
                'id'          => $t->author->id,
                'full_name'   => $t->author->full_name,
                'designation' => $t->author->membershipCategory?->designation,
                'avatar'      => $t->author->avatar,
            ],
            'created_at' => $t->created_at->toIso8601String(),
        ]);

        return $this->paginated($transformed);
    }

    /**
     * View a single topic with replies.
     */
    public function showTopic(ForumTopic $forumTopic): JsonResponse
    {
        $forumTopic->incrementViews();
        $forumTopic->load(['author.membershipCategory', 'category']);

        $replies = $forumTopic->replies()
            ->with(['author.membershipCategory', 'children.author.membershipCategory'])
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->get()
            ->map(fn($r) => $this->formatReply($r));

        return $this->success([
            'topic' => [
                'id'          => $forumTopic->id,
                'title'       => $forumTopic->title,
                'slug'        => $forumTopic->slug,
                'body'        => $forumTopic->body,
                'is_pinned'   => $forumTopic->is_pinned,
                'is_locked'   => $forumTopic->is_locked,
                'views_count' => $forumTopic->views_count,
                'category'    => [
                    'id'   => $forumTopic->category->id,
                    'name' => $forumTopic->category->name,
                    'slug' => $forumTopic->category->slug,
                ],
                'author' => [
                    'id'          => $forumTopic->author->id,
                    'full_name'   => $forumTopic->author->full_name,
                    'designation' => $forumTopic->author->membershipCategory?->designation,
                    'avatar'      => $forumTopic->author->avatar,
                ],
                'created_at' => $forumTopic->created_at->toIso8601String(),
            ],
            'replies'      => $replies,
            'replies_count' => $forumTopic->replies()->count(),
        ]);
    }

    /**
     * Create a new topic.
     */
    public function createTopic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:forum_categories,id'],
            'title'        => ['required', 'string', 'max:255'],
            'body'         => ['required', 'string'],
        ]);

        $topic = ForumTopic::create([
            'category_id' => $validated['category_id'],
            'user_id'     => $request->user()->id,
            'title'       => $validated['title'],
            'body'        => $validated['body'],
        ]);

        $topic->load('author.membershipCategory');

        return $this->created([
            'id'    => $topic->id,
            'title' => $topic->title,
            'slug'  => $topic->slug,
        ], 'Topic created.');
    }

    /**
     * Reply to a topic.
     */
    public function reply(Request $request, ForumTopic $forumTopic): JsonResponse
    {
        if ($forumTopic->is_locked) {
            return $this->error('This topic is locked and cannot receive new replies.');
        }

        $validated = $request->validate([
            'body'      => ['required', 'string'],
            'parent_id' => ['nullable', 'exists:forum_replies,id'],
        ]);

        $reply = ForumReply::create([
            'topic_id'  => $forumTopic->id,
            'user_id'   => $request->user()->id,
            'body'      => $validated['body'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        $forumTopic->update(['last_reply_at' => now()]);

        $reply->load('author.membershipCategory');

        return $this->created($this->formatReply($reply), 'Reply posted.');
    }

    /**
     * Edit own topic.
     */
    public function updateTopic(Request $request, ForumTopic $forumTopic): JsonResponse
    {
        if ($forumTopic->user_id !== $request->user()->id) {
            return $this->forbidden('You can only edit your own topics.');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body'  => ['sometimes', 'string'],
        ]);

        $forumTopic->update($validated);

        return $this->success(null, 'Topic updated.');
    }

    /**
     * Edit own reply.
     */
    public function updateReply(Request $request, ForumReply $forumReply): JsonResponse
    {
        if ($forumReply->user_id !== $request->user()->id) {
            return $this->forbidden('You can only edit your own replies.');
        }

        $request->validate(['body' => ['required', 'string']]);

        $forumReply->update(['body' => $request->body]);

        return $this->success(null, 'Reply updated.');
    }

    /**
     * Delete own topic.
     */
    public function deleteTopic(Request $request, ForumTopic $forumTopic): JsonResponse
    {
        if ($forumTopic->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbidden('You can only delete your own topics.');
        }

        $forumTopic->delete();
        return $this->success(null, 'Topic deleted.');
    }

    /**
     * Delete own reply.
     */
    public function deleteReply(Request $request, ForumReply $forumReply): JsonResponse
    {
        if ($forumReply->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return $this->forbidden('You can only delete your own replies.');
        }

        $forumReply->delete();
        return $this->success(null, 'Reply deleted.');
    }

    private function formatReply($reply): array
    {
        return [
            'id'       => $reply->id,
            'body'     => $reply->body,
            'author'   => [
                'id'          => $reply->author->id,
                'full_name'   => $reply->author->full_name,
                'designation' => $reply->author->membershipCategory?->designation,
                'avatar'      => $reply->author->avatar,
            ],
            'children'   => $reply->children->map(fn($c) => $this->formatReply($c)),
            'created_at' => $reply->created_at->toIso8601String(),
            'updated_at' => $reply->updated_at->toIso8601String(),
        ];
    }
}
