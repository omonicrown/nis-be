<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MembershipCategoryResource;
use App\Http\Resources\SubgroupResource;
use App\Models\ExecutivePosition;
use App\Models\MembershipCategory;
use App\Models\Subgroup;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    use ApiResponse;

    /**
     * Get all active membership categories.
     * Needed for registration form.
     */
    public function membershipCategories(): JsonResponse
    {
        $categories = MembershipCategory::where('is_active', true)
            ->orderBy('rank', 'desc')
            ->get();

        return $this->success(MembershipCategoryResource::collection($categories));
    }

    /**
     * Get all active subgroups.
     * Needed for registration form.
     */
    public function subgroups(): JsonResponse
    {
        $subgroups = Subgroup::where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->success(SubgroupResource::collection($subgroups));
    }

    /**
     * Get current executive members.
     * For the public leadership page.
     */
    public function executives(): JsonResponse
    {
        $executives = ExecutivePosition::with('user.membershipCategory')
            ->current()
            ->get()
            ->map(fn($exec) => [
                'id'             => $exec->id,
                'name'           => $exec->user->full_name,
                'title'          => $exec->title,
                'designation'    => $exec->designation,
                'bio'            => $exec->bio,
                'photo'          => $exec->photo,
                'position_order' => $exec->position_order,
            ]);

        return $this->success($executives);
    }
}
