<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\MemberProfile;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use ApiResponse;

    /**
     * List all members with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['membershipCategory', 'role', 'profile', 'subgroups'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->category, fn($q, $cat) => $q->whereHas('membershipCategory', fn($mq) => $mq->where('slug', $cat)))
            ->when($request->search, fn($q, $search) => $q->search($search))
            ->when($request->subgroup, fn($q, $sg) => $q->whereHas('subgroups', fn($sq) => $sq->where('slug', $sg)))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        $members = $query->paginate($request->per_page ?? 20);

        return $this->paginated($members);
    }

    /**
     * View single member details.
     */
    public function show(User $user): JsonResponse
    {
        $user->load([
            'membershipCategory',
            'role',
            'profile',
            'subgroups',
            'executivePositions',
            'approvedBy',
        ]);

        return $this->success(new UserResource($user));
    }

    /**
     * Approve a pending member.
     */
    public function approve(Request $request, User $user): JsonResponse
    {
        $status = $user->status instanceof UserStatus ? $user->status->value : $user->status;
        if ($status !== 'pending') {
            return $this->error('Only pending members can be approved.');
        }

        $user->update([
            'status'      => UserStatus::ACTIVE,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        // TODO: Send approval notification email to user

        $user->load(['membershipCategory', 'role']);

        return $this->success(new UserResource($user), 'Member approved successfully.');
    }

    /**
     * Reject a pending member.
     */
    public function reject(Request $request, User $user): JsonResponse
    {
        $status = $user->status instanceof UserStatus ? $user->status->value : $user->status;
        if ($status !== 'pending') {
            return $this->error('Only pending members can be rejected.');
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update([
            'status' => UserStatus::REJECTED,
        ]);

        // TODO: Send rejection notification email with reason

        return $this->success(null, 'Member registration rejected.');
    }

    /**
     * Suspend an active member.
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update(['status' => UserStatus::SUSPENDED]);

        // Revoke all tokens
        $user->tokens()->delete();

        return $this->success(null, 'Member suspended successfully.');
    }

    /**
     * Reactivate a suspended/inactive member.
     */
    public function reactivate(User $user): JsonResponse
    {
        $status = $user->status instanceof UserStatus ? $user->status->value : $user->status;
        if (!in_array($status, ['suspended', 'inactive'])) {
            return $this->error('Only suspended or inactive members can be reactivated.');
        }

        $user->update(['status' => UserStatus::ACTIVE]);

        return $this->success(null, 'Member reactivated successfully.');
    }

    /**
     * Update member's role.
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $user->update(['role_id' => $request->role_id]);

        $user->load('role');

        return $this->success(new UserResource($user), 'Member role updated.');
    }

    /**
     * Update member's membership category.
     */
    public function updateCategory(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'membership_category_id' => ['required', 'exists:membership_categories,id'],
        ]);

        $user->update(['membership_category_id' => $request->membership_category_id]);

        $user->load('membershipCategory');

        return $this->success(new UserResource($user), 'Membership category updated.');
    }

    /**
     * Get pending members count (for dashboard).
     */
    public function pendingCount(): JsonResponse
    {
        $count = User::pending()->count();

        return $this->success(['pending_count' => $count]);
    }

    /**
     * Bulk approve members.
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $updated = User::whereIn('id', $request->user_ids)
            ->where('status', UserStatus::PENDING)
            ->update([
                'status'      => UserStatus::ACTIVE,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ]);

        return $this->success(
            ['approved_count' => $updated],
            "{$updated} member(s) approved successfully."
        );
    }

    /**
     * Admin: Update a member's profile on their behalf.
     */
    public function updateProfile(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'first_name'             => ['sometimes', 'string', 'max:100'],
            'last_name'              => ['sometimes', 'string', 'max:100'],
            'other_names'            => ['nullable', 'string', 'max:100'],
            'phone'                  => ['nullable', 'string', 'max:20'],
            'gender'                 => ['nullable', 'in:male,female'],
            'surcon_reg_no'          => ['nullable', 'string', 'max:50'],
            'nis_membership_id'      => ['nullable', 'string', 'max:50'],
            'membership_category_id' => ['sometimes', 'exists:membership_categories,id'],
            'office_address'         => ['nullable', 'string', 'max:500'],
            'residential_address'    => ['nullable', 'string', 'max:500'],
            'date_of_birth'          => ['nullable', 'date', 'before:today'],
            'bio'                    => ['nullable', 'string', 'max:1000'],
            'specialization'         => ['nullable', 'string', 'max:255'],
            'firm_name'              => ['nullable', 'string', 'max:255'],
            'subgroup_ids'           => ['nullable', 'array'],
            'subgroup_ids.*'         => ['exists:subgroups,id'],
        ]);

        $userFields = collect($validated)->only([
            'first_name',
            'last_name',
            'other_names',
            'phone',
            'gender',
            'surcon_reg_no',
            'nis_membership_id',
            'membership_category_id',
        ])->filter(fn($v) => $v !== null)->toArray();

        if (!empty($userFields)) {
            $user->update($userFields);
        }

        $profileFields = collect($validated)->only([
            'office_address',
            'residential_address',
            'date_of_birth',
            'bio',
            'specialization',
            'firm_name',
        ])->toArray();

        if (!empty($profileFields)) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileFields
            );
        }

        if (isset($validated['subgroup_ids'])) {
            $user->subgroups()->sync($validated['subgroup_ids']);
        }

        $user->refresh();
        $user->load(['membershipCategory', 'role', 'profile', 'subgroups']);

        return $this->success(new UserResource($user), 'Member profile updated.');
    }
}
