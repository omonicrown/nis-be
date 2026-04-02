<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\MembershipCategory;
use App\Models\Subgroup;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    use ApiResponse;

    /**
     * Browse the member directory.
     * Respects each member's privacy settings.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search'    => ['nullable', 'string', 'max:100'],
            'category'  => ['nullable', 'string', 'exists:membership_categories,slug'],
            'subgroup'  => ['nullable', 'string', 'exists:subgroups,slug'],
            'gender'    => ['nullable', 'in:male,female'],
            'letter'    => ['nullable', 'string', 'max:1'],
            'sort_by'   => ['nullable', 'in:name,category,created_at'],
            'sort_dir'  => ['nullable', 'in:asc,desc'],
            'per_page'  => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = User::with(['membershipCategory', 'profile', 'subgroups'])
            ->active()
            ->whereHas('profile', function ($q) {
                $q->where('show_in_directory', true);
            });

        // Search by name, NIS ID, or SURCON
        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('nis_membership_id', 'like', "%{$search}%")
                  ->orWhere('surcon_reg_no', 'like', "%{$search}%");
            });
        }

        // Filter by membership category
        if ($category = $request->category) {
            $query->whereHas('membershipCategory', fn($q) => $q->where('slug', $category));
        }

        // Filter by subgroup
        if ($subgroup = $request->subgroup) {
            $query->whereHas('subgroups', fn($q) => $q->where('slug', $subgroup));
        }

        // Filter by gender
        if ($gender = $request->gender) {
            $query->where('gender', $gender);
        }

        // Filter by first letter of last name
        if ($letter = $request->letter) {
            $query->where('last_name', 'like', "{$letter}%");
        }

        // Sorting
        $sortBy = match ($request->sort_by) {
            'name'       => 'last_name',
            'category'   => 'membership_category_id',
            'created_at' => 'created_at',
            default      => 'last_name',
        };
        $query->orderBy($sortBy, $request->sort_dir ?? 'asc');

        $members = $query->paginate($request->per_page ?? 20);

        // Transform with privacy controls
        $transformed = $members->through(function ($user) {
            return $this->applyPrivacy($user);
        });

        return $this->paginated($transformed);
    }

    /**
     * View a single member's public profile.
     */
    public function show(User $user): JsonResponse
    {
        // Must be active and visible in directory
        if (!$user->isApproved()) {
            return $this->notFound('Member not found.');
        }

        $user->load(['membershipCategory', 'profile', 'subgroups', 'currentExecutivePosition']);

        $profile = $user->profile;
        if ($profile && !$profile->show_in_directory) {
            return $this->notFound('This member\'s profile is not publicly visible.');
        }

        return $this->success($this->applyPrivacy($user, true));
    }

    /**
     * Get filter options for the directory.
     */
    public function filters(): JsonResponse
    {
        $categories = MembershipCategory::where('is_active', true)
            ->orderBy('rank', 'desc')
            ->get(['id', 'name', 'slug', 'designation']);

        $subgroups = Subgroup::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'full_name']);

        // Alphabet for letter filter
        $letters = User::active()
            ->selectRaw("UPPER(LEFT(last_name, 1)) as letter")
            ->groupBy('letter')
            ->orderBy('letter')
            ->pluck('letter');

        // Counts by category
        $categoryCounts = User::active()
            ->selectRaw('membership_category_id, COUNT(*) as count')
            ->groupBy('membership_category_id')
            ->pluck('count', 'membership_category_id');

        return $this->success([
            'categories'      => $categories,
            'subgroups'       => $subgroups,
            'letters'         => $letters,
            'category_counts' => $categoryCounts,
            'total_members'   => User::active()->count(),
        ]);
    }

    /**
     * Apply privacy settings to a user's public data.
     */
    private function applyPrivacy(User $user, bool $detailed = false): array
    {
        $profile = $user->profile;

        $data = [
            'id'                  => $user->id,
            'full_name'           => $user->full_name,
            'first_name'          => $user->first_name,
            'last_name'           => $user->last_name,
            'gender'              => $user->gender,
            'avatar'              => $user->avatar,
            'surcon_reg_no'       => $user->surcon_reg_no,
            'nis_membership_id'   => $user->nis_membership_id,
            'membership_category' => $user->membershipCategory ? [
                'name'        => $user->membershipCategory->name,
                'designation' => $user->membershipCategory->designation,
            ] : null,
            'subgroups' => $user->subgroups->map(fn($sg) => [
                'name'      => $sg->name,
                'full_name' => $sg->full_name,
            ]),
        ];

        // Apply privacy controls
        if ($profile) {
            $data['email']               = $profile->show_email ? $user->email : null;
            $data['phone']               = $profile->show_phone ? $user->phone : null;
            $data['office_address']      = $profile->show_office_address ? $profile->office_address : null;
            $data['residential_address'] = $profile->show_residential_address ? $profile->residential_address : null;

            if ($detailed) {
                $data['bio']              = $profile->bio;
                $data['specialization']   = $profile->specialization;
                $data['firm_name']        = $profile->firm_name;
            }
        }

        // Executive position if any
        if ($detailed && $user->currentExecutivePosition) {
            $data['executive_position'] = [
                'title'       => $user->currentExecutivePosition->title,
                'designation' => $user->currentExecutivePosition->designation,
            ];
        }

        return $data;
    }
}
