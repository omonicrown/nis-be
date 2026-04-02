<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'slug'               => $this->slug,
            'excerpt'            => $this->excerpt,
            'body'               => $this->when($this->shouldShowBody($request), $this->body),
            'featured_image_url' => $this->featured_image_url,
            'status'             => $this->status,
            'visibility'         => $this->visibility,
            'category'           => $this->category,
            'is_pinned'          => $this->is_pinned,
            'published_at'       => $this->published_at?->toIso8601String(),
            'author'             => $this->whenLoaded('author', fn() => [
                'id'        => $this->author->id,
                'full_name' => $this->author->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Show full body only on single post view, not on list.
     */
    private function shouldShowBody(Request $request): bool
    {
        // Show body if route is a single post show route
        return $request->route('post') !== null
            || $request->route('slug') !== null
            || $request->get('full_body');
    }
}
