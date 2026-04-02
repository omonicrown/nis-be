<?php

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    /**
     * Upload a file to Cloudinary.
     *
     * @param UploadedFile $file
     * @param string $folder  e.g. "avatars", "minutes", "resources"
     * @param array $options  Additional Cloudinary options
     * @return array  ['public_id' => '...', 'url' => '...', 'secure_url' => '...']
     */
    public function upload(UploadedFile $file, string $folder = 'general', array $options = []): array
    {
        $defaults = [
            'folder' => "nis-oyo/{$folder}",
        ];

        $result = Cloudinary::upload(
            $file->getRealPath(),
            array_merge($defaults, $options)
        );

        return [
            'public_id'  => $result->getPublicId(),
            'url'        => $result->getPath(),
            'secure_url' => $result->getSecurePath(),
            'size'       => $result->getSize(),
            'format'     => $result->getExtension(),
        ];
    }

    /**
     * Upload an image with transformations.
     */
    public function uploadImage(UploadedFile $file, string $folder = 'images', array $options = []): array
    {
        $defaults = [
            'folder'         => "nis-oyo/{$folder}",
            'resource_type'  => 'image',
            'transformation' => [
                'quality' => 'auto',
                'fetch_format' => 'auto',
            ],
        ];

        $result = Cloudinary::upload(
            $file->getRealPath(),
            array_merge($defaults, $options)
        );

        return [
            'public_id'  => $result->getPublicId(),
            'url'        => $result->getPath(),
            'secure_url' => $result->getSecurePath(),
            'size'       => $result->getSize(),
            'format'     => $result->getExtension(),
        ];
    }

    /**
     * Upload avatar with specific sizing.
     */
    public function uploadAvatar(UploadedFile $file): array
    {
        return $this->uploadImage($file, 'avatars', [
            'transformation' => [
                'width'   => 400,
                'height'  => 400,
                'crop'    => 'fill',
                'gravity' => 'face',
                'quality' => 'auto',
            ],
        ]);
    }

    /**
     * Upload a document (PDF, DOCX, etc).
     */
    public function uploadDocument(UploadedFile $file, string $folder = 'documents'): array
    {
        $result = Cloudinary::upload(
            $file->getRealPath(),
            [
                'folder'        => "nis-oyo/{$folder}",
                'resource_type' => 'raw',
            ]
        );

        return [
            'public_id'     => $result->getPublicId(),
            'url'           => $result->getPath(),
            'secure_url'    => $result->getSecurePath(),
            'size'          => $result->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'format'        => $file->getClientOriginalExtension(),
        ];
    }

    /**
     * Delete a file from Cloudinary.
     *
     * @param string $publicId
     * @param string $resourceType  'image', 'raw', 'video'
     */
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        try {
            Cloudinary::destroy($publicId, ['resource_type' => $resourceType]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete a document (raw resource type).
     */
    public function deleteDocument(string $publicId): bool
    {
        return $this->delete($publicId, 'raw');
    }
}
