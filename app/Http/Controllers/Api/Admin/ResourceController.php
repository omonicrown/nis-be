<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceResource;
use App\Models\Resource;
use App\Services\CloudinaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    use ApiResponse;

    private CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Resource::with('uploader')
            ->when($request->category, fn($q, $c) => $q->byCategory($c))
            ->when($request->visibility, fn($q, $v) => $q->where('visibility', $v))
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        return $this->paginated($query->paginate($request->per_page ?? 20));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category'    => ['required', 'string', 'in:standard,guideline,form,template,research,other'],
            'visibility'  => ['nullable', 'in:public,members_only'],
            'file'        => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip', 'max:20480'], // 20MB
        ]);

        $result = $this->cloudinary->uploadDocument($request->file('file'), 'resources');

        $resource = Resource::create([
            'title'          => $request->title,
            'description'    => $request->description,
            'category'       => $request->category,
            'visibility'     => $request->visibility ?? 'members_only',
            'file_url'       => $result['secure_url'],
            'file_public_id' => $result['public_id'],
            'file_name'      => $result['original_name'],
            'file_type'      => $result['format'],
            'file_size'      => $result['size'] ?? null,
            'uploaded_by'    => $request->user()->id,
        ]);

        return $this->created(new ResourceResource($resource), 'Resource uploaded.');
    }

    public function show(Resource $resource): JsonResponse
    {
        $resource->load('uploader');
        return $this->success(new ResourceResource($resource));
    }

    public function update(Request $request, Resource $resource): JsonResponse
    {
        $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category'    => ['sometimes', 'string', 'in:standard,guideline,form,template,research,other'],
            'visibility'  => ['nullable', 'in:public,members_only'],
        ]);

        $resource->update($request->only(['title', 'description', 'category', 'visibility']));

        return $this->success(new ResourceResource($resource), 'Resource updated.');
    }

    /**
     * Replace the file on an existing resource.
     */
    public function replaceFile(Request $request, Resource $resource): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip', 'max:20480'],
        ]);

        // Delete old file
        $this->cloudinary->deleteDocument($resource->file_public_id);

        $result = $this->cloudinary->uploadDocument($request->file('file'), 'resources');

        $resource->update([
            'file_url'       => $result['secure_url'],
            'file_public_id' => $result['public_id'],
            'file_name'      => $result['original_name'],
            'file_type'      => $result['format'],
            'file_size'      => $result['size'] ?? null,
        ]);

        return $this->success(new ResourceResource($resource), 'File replaced.');
    }

    public function destroy(Resource $resource): JsonResponse
    {
        $this->cloudinary->deleteDocument($resource->file_public_id);
        $resource->delete();
        return $this->success(null, 'Resource deleted.');
    }
}
