<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MembershipCategoryResource;
use App\Http\Resources\SubgroupResource;
use App\Models\ExecutivePosition;
use App\Models\MembershipCategory;
use App\Models\Subgroup;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Search for a surveyor by name (public, no auth).
     * GET /api/public/search-surveyor?q=Ajibade
     */
    public function searchSurveyor(Request $request): JsonResponse
    {
        $search = $request->get('q');

        if (!$search || strlen($search) < 2) {
            return $this->error('Please provide at least 2 characters to search.', 422);
        }

        $surveyors = User::where('status', 'active')
            ->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('other_names', 'like', "%{$search}%")
                    ->orWhere('nis_membership_id', 'like', "%{$search}%")
                    ->orWhere('surcon_reg_no', 'like', "%{$search}%");
            })
            ->select('id', 'first_name', 'last_name', 'other_names', 'email', 'phone', 'nis_membership_id', 'surcon_reg_no', 'membership_category_id')
            ->with('membershipCategory:id,name,designation')
            ->limit(20)
            ->get()
            ->map(fn($user) => [
                'full_name'           => $user->full_name,
                'email'               => $user->email,
                'phone'               => $user->phone,
                'nis_membership_id'   => $user->nis_membership_id,
                'surcon_reg_no'       => $user->surcon_reg_no,
                'membership_category' => $user->membershipCategory?->name,
                'designation'         => $user->membershipCategory?->designation,
            ]);

        return $this->success($surveyors, "{$surveyors->count()} surveyor(s) found.");
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
