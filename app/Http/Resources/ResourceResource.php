<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'description'    => $this->description,
            'category'       => $this->category,
            'visibility'     => $this->visibility,
            'file_url'       => $this->file_url,
            'file_name'      => $this->file_name,
            'file_type'      => $this->file_type,
            'file_size'      => $this->file_size,
            'file_size_formatted' => $this->formattedFileSize(),
            'download_count' => $this->download_count,
            'uploaded_by'    => $this->whenLoaded('uploader', fn() => [
                'id'        => $this->uploader->id,
                'full_name' => $this->uploader->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
