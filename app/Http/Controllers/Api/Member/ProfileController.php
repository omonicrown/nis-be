<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\CloudinaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    use ApiResponse;

    private CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * Get the authenticated member's full profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'membershipCategory',
            'role',
            'profile',
            'subgroups',
            'currentExecutivePosition',
        ]);

        return $this->success(new UserResource($user));
    }

    /**
     * Update the authenticated member's profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated) {
            // Update user table fields
            $userFields = collect($validated)->only([
                'first_name',
                'last_name',
                'other_names',
                'phone',
                'gender',
            ])->filter(fn($value) => $value !== null)->toArray();

            if (!empty($userFields)) {
                $user->update($userFields);
            }

            // Update or create profile
            $profileFields = collect($validated)->only([
                'office_address',
                'residential_address',
                'date_of_birth',
                'bio',
                'specialization',
                'firm_name',
                'year_of_registration',
                'show_email',
                'show_phone',
                'show_office_address',
                'show_residential_address',
                'show_in_directory',
            ])->toArray();

            if (!empty($profileFields)) {
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileFields
                );
            }

            // Sync subgroups
            if (isset($validated['subgroup_ids'])) {
                $user->subgroups()->sync($validated['subgroup_ids']);
            }

            // Check if profile is completed
            $this->checkProfileCompletion($user);
        });

        $user->refresh();
        $user->load(['membershipCategory', 'role', 'profile', 'subgroups']);

        return $this->success(new UserResource($user), 'Profile updated successfully.');
    }

    /**
     * Upload or update avatar via Cloudinary.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // 2MB
        ]);

        $user = $request->user();

        // Delete old avatar from Cloudinary if exists
        if ($user->avatar_public_id) {
            $this->cloudinary->delete($user->avatar_public_id);
        }

        $result = $this->cloudinary->uploadAvatar($request->file('avatar'));

        $user->update([
            'avatar'           => $result['secure_url'],
            'avatar_public_id' => $result['public_id'],
        ]);

        return $this->success([
            'avatar_url' => $result['secure_url'],
        ], 'Avatar updated successfully.');
    }

    /**
     * Remove avatar.
     */
    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_public_id) {
            $this->cloudinary->delete($user->avatar_public_id);
        }

        $user->update([
            'avatar'           => null,
            'avatar_public_id' => null,
        ]);

        return $this->success(null, 'Avatar removed.');
    }

    /**
     * Get profile completion status with missing fields.
     */
    public function completionStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profile');
        $profile = $user->profile;

        $fields = [
            'first_name'      => ['filled' => !empty($user->first_name), 'label' => 'First Name'],
            'last_name'       => ['filled' => !empty($user->last_name), 'label' => 'Last Name'],
            'email'           => ['filled' => !empty($user->email), 'label' => 'Email'],
            'phone'           => ['filled' => !empty($user->phone), 'label' => 'Phone Number'],
            'gender'          => ['filled' => !empty($user->gender), 'label' => 'Gender'],
            'surcon_reg_no'   => ['filled' => !empty($user->surcon_reg_no), 'label' => 'SURCON Reg. No'],
            'office_address'  => ['filled' => !empty($profile?->office_address), 'label' => 'Office Address'],
            'residential_address' => ['filled' => !empty($profile?->residential_address), 'label' => 'Residential Address'],
            'date_of_birth'   => ['filled' => !empty($profile?->date_of_birth), 'label' => 'Date of Birth'],
            'avatar'          => ['filled' => !empty($user->avatar), 'label' => 'Profile Photo'],
        ];

        $filledCount = collect($fields)->where('filled', true)->count();
        $totalCount = count($fields);
        $percentage = round(($filledCount / $totalCount) * 100);

        $missing = collect($fields)
            ->where('filled', false)
            ->map(fn($field) => $field['label'])
            ->values();

        return $this->success([
            'percentage'    => $percentage,
            'filled_count'  => $filledCount,
            'total_fields'  => $totalCount,
            'is_complete'   => $percentage === 100,
            'missing_fields' => $missing,
            'is_migrated'   => $user->is_migrated,
        ]);
    }

    /**
     * Check and update profile completion status.
     */
    private function checkProfileCompletion($user): void
    {
        $user->refresh();
        $profile = $user->profile;

        $isComplete = !empty($user->first_name)
            && !empty($user->last_name)
            && !empty($user->phone)
            && !empty($user->email)
            && !empty($profile?->office_address);

        if ($user->profile_completed !== $isComplete) {
            $user->update(['profile_completed' => $isComplete]);
        }
    }
}
