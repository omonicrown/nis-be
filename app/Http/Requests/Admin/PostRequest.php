<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $slugUnique = $this->route('post')
            ? 'unique:posts,slug,' . $this->route('post')->id
            : 'unique:posts,slug';

        return [
            'title'      => ['required', 'string', 'max:255'],
            'slug'       => ['nullable', 'string', 'max:255', $slugUnique],
            'excerpt'    => ['nullable', 'string', 'max:500'],
            'body'       => ['required', 'string'],
            'status'     => ['nullable', 'in:draft,published,archived'],
            'visibility' => ['nullable', 'in:public,members_only'],
            'category'   => ['nullable', 'string', 'in:news,update,circular,newsletter,other'],
            'is_pinned'  => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
