<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Meeting;
use App\Models\MembershipCategory;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Resource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Membership growth and breakdown report.
     */
    public function membershipReport(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        // By category
        $byCategory = MembershipCategory::withCount([
            'members as total' => fn($q) => $q->active(),
        ])->orderByDesc('rank')->get()->map(fn($c) => [
            'category'    => $c->name,
            'designation' => $c->designation,
            'total'       => $c->total,
            'annual_fee'  => $c->annual_fee,
        ]);

        // By gender
        $byGender = User::active()
            ->selectRaw("gender, COUNT(*) as count")
            ->groupBy('gender')
            ->pluck('count', 'gender');

        // Monthly registrations for the year
        $monthlyGrowth = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyGrowth[] = [
                'month' => $m,
                'label' => date('M', mktime(0, 0, 0, $m, 1)),
                'new_members' => User::whereYear('created_at', $year)
                    ->whereMonth('created_at', $m)->count(),
            ];
        }

        // By subgroup
        $bySubgroup = DB::table('member_subgroup')
            ->join('subgroups', 'subgroups.id', '=', 'member_subgroup.subgroup_id')
            ->join('users', 'users.id', '=', 'member_subgroup.user_id')
            ->where('users.status', 'active')
            ->selectRaw('subgroups.name, subgroups.full_name, COUNT(*) as count')
            ->groupBy('subgroups.name', 'subgroups.full_name')
            ->get();

        return $this->success([
            'year'           => (int) $year,
            'total_active'   => User::active()->count(),
            'total_pending'  => User::pending()->count(),
            'total_suspended' => User::where('status', 'suspended')->count(),
            'by_category'    => $byCategory,
            'by_gender'      => $byGender,
            'by_subgroup'    => $bySubgroup,
            'monthly_growth' => $monthlyGrowth,
            'migrated_count' => User::where('is_migrated', true)->count(),
            'profile_completed_rate' => User::active()->count() > 0
                ? round(User::active()->where('profile_completed', true)->count() / User::active()->count() * 100, 1)
                : 0,
        ]);
    }

    /**
     * Attendance report for a period.
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        $meetings = Meeting::byYear($year)
            ->withCount([
                'attendances as present_count' => fn($q) => $q->present(),
                'attendances as absent_count'  => fn($q) => $q->absent(),
                'attendances as apology_count' => fn($q) => $q->apology(),
                'attendances as total_count',
            ])
            ->orderBy('meeting_date')
            ->get()
            ->map(fn($m) => [
                'id'            => $m->id,
                'title'         => $m->title,
                'meeting_date'  => $m->meeting_date->format('Y-m-d'),
                'type'          => $m->type,
                'present'       => $m->present_count,
                'absent'        => $m->absent_count,
                'apology'       => $m->apology_count,
                'total'         => $m->total_count,
                'attendance_rate' => $m->total_count > 0
                    ? round($m->present_count / $m->total_count * 100, 1) : 0,
            ]);

        // Top attenders
        $topAttenders = Attendance::present()
            ->whereHas('meeting', fn($q) => $q->byYear($year))
            ->selectRaw('user_id, COUNT(*) as present_count')
            ->groupBy('user_id')
            ->orderByDesc('present_count')
            ->limit(20)
            ->with('user:id,first_name,last_name,nis_membership_id')
            ->get()
            ->map(fn($a) => [
                'user_id'           => $a->user_id,
                'full_name'         => $a->user->full_name,
                'nis_membership_id' => $a->user->nis_membership_id,
                'meetings_attended' => $a->present_count,
            ]);

        // Lowest attenders
        $lowestAttenders = Attendance::where('status', '!=', 'present')
            ->whereHas('meeting', fn($q) => $q->byYear($year))
            ->selectRaw('user_id, COUNT(*) as missed_count')
            ->groupBy('user_id')
            ->orderByDesc('missed_count')
            ->limit(20)
            ->with('user:id,first_name,last_name,nis_membership_id')
            ->get()
            ->map(fn($a) => [
                'user_id'           => $a->user_id,
                'full_name'         => $a->user->full_name,
                'nis_membership_id' => $a->user->nis_membership_id,
                'meetings_missed'   => $a->missed_count,
            ]);

        return $this->success([
            'year'             => (int) $year,
            'total_meetings'   => $meetings->count(),
            'average_attendance' => $meetings->count() > 0
                ? round($meetings->avg('attendance_rate'), 1) : 0,
            'meetings'         => $meetings,
            'top_attenders'    => $topAttenders,
            'lowest_attenders' => $lowestAttenders,
        ]);
    }

    /**
     * Financial/payment report.
     */
    public function financialReport(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        $yearPayments = Payment::byYear($year)->completed();

        // Monthly breakdown
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthPayments = (clone $yearPayments)->whereMonth('paid_at', $m);
            $monthly[] = [
                'month'   => $m,
                'label'   => date('M', mktime(0, 0, 0, $m, 1)),
                'revenue' => (clone $monthPayments)->sum('amount'),
                'count'   => (clone $monthPayments)->count(),
            ];
        }

        // By category
        $byCategory = MembershipCategory::all()->map(function ($cat) use ($year) {
            $totalMembers = $cat->members()->active()->count();
            $paidMembers = Payment::completed()->byYear($year)->byType('membership_dues')
                ->whereHas('user', fn($q) => $q->where('membership_category_id', $cat->id))
                ->distinct('user_id')->count('user_id');

            return [
                'category'       => $cat->name,
                'total_members'  => $totalMembers,
                'paid_members'   => $paidMembers,
                'unpaid_members' => $totalMembers - $paidMembers,
                'expected_revenue' => $totalMembers * $cat->annual_fee,
                'actual_revenue' => Payment::completed()->byYear($year)->byType('membership_dues')
                    ->whereHas('user', fn($q) => $q->where('membership_category_id', $cat->id))
                    ->sum('amount'),
                'collection_rate' => $totalMembers > 0
                    ? round($paidMembers / $totalMembers * 100, 1) : 0,
            ];
        });

        return $this->success([
            'year'            => (int) $year,
            'total_revenue'   => (clone $yearPayments)->sum('amount'),
            'total_transactions' => (clone $yearPayments)->count(),
            'by_method' => [
                'paystack'      => Payment::byYear($year)->completed()->byMethod('paystack')->sum('amount'),
                'bank_transfer' => Payment::byYear($year)->completed()->byMethod('bank_transfer')->sum('amount'),
                'cash'          => Payment::byYear($year)->completed()->byMethod('cash')->sum('amount'),
            ],
            'by_type' => [
                'membership_dues'    => Payment::byYear($year)->completed()->byType('membership_dues')->sum('amount'),
                'event_registration' => Payment::byYear($year)->completed()->byType('event_registration')->sum('amount'),
                'donation'           => Payment::byYear($year)->completed()->byType('donation')->sum('amount'),
                'other'              => Payment::byYear($year)->completed()->byType('other')->sum('amount'),
            ],
            'monthly'     => $monthly,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * Engagement report — forum, events, resources.
     */
    public function engagementReport(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        return $this->success([
            'year' => (int) $year,
            'forum' => [
                'total_topics'       => ForumTopic::whereYear('created_at', $year)->count(),
                'total_replies'      => ForumReply::whereYear('created_at', $year)->count(),
                'active_participants' => ForumTopic::whereYear('created_at', $year)
                    ->distinct('user_id')->count('user_id')
                    + ForumReply::whereYear('created_at', $year)
                    ->distinct('user_id')->count('user_id'),
            ],
            'events' => [
                'total_events'        => Event::whereYear('start_date', $year)->count(),
                'total_registrations' => DB::table('event_registrations')
                    ->whereYear('created_at', $year)
                    ->where('status', 'registered')->count(),
            ],
            'content' => [
                'published_posts'    => Post::published()->whereYear('published_at', $year)->count(),
                'total_resources'    => Resource::whereYear('created_at', $year)->count(),
                'total_downloads'    => Resource::sum('download_count'),
            ],
            'feedback' => [
                'total_feedbacks'  => Feedback::whereYear('created_at', $year)->count(),
                'pending'          => Feedback::pending()->count(),
                'resolved'         => Feedback::whereYear('created_at', $year)->where('status', 'resolved')->count(),
                'by_type'          => Feedback::whereYear('created_at', $year)
                    ->selectRaw("type, COUNT(*) as count")
                    ->groupBy('type')
                    ->pluck('count', 'type'),
            ],
        ]);
    }

    /**
     * Combined overview — all key metrics on one page.
     */
    public function overview(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        return $this->success([
            'year' => (int) $year,
            'members' => [
                'total_active'    => User::active()->count(),
                'pending'         => User::pending()->count(),
                'new_this_year'   => User::whereYear('created_at', $year)->count(),
                'fellows'         => User::byCategory('fellow')->active()->count(),
                'members'         => User::byCategory('member')->active()->count(),
                'associates'      => User::byCategory('associate')->active()->count(),
                'probationers'    => User::byCategory('probationer')->active()->count(),
                'students'        => User::byCategory('student')->active()->count(),
            ],
            'finance' => [
                'revenue_this_year' => Payment::completed()->byYear($year)->sum('amount'),
                'pending_payments'  => Payment::pending()->sum('amount'),
                'pending_manual'    => Payment::manualPending()->count(),
            ],
            'meetings' => [
                'held_this_year'       => Meeting::byYear($year)->where('status', 'completed')->count(),
                'upcoming'             => Meeting::upcoming()->count(),
                'avg_attendance_rate'  => $this->avgAttendanceRate($year),
            ],
            'content' => [
                'published_posts'      => Post::published()->count(),
                'upcoming_events'      => Event::upcoming()->count(),
                'total_resources'      => Resource::count(),
                'active_announcements' => \App\Models\Announcement::active()->count(),
            ],
            'community' => [
                'forum_topics'   => ForumTopic::count(),
                'forum_replies'  => ForumReply::count(),
                'pending_feedback' => Feedback::pending()->count(),
            ],
        ]);
    }

    private function avgAttendanceRate(int $year): float
    {
        $meetings = Meeting::byYear($year)->where('status', 'completed')
            ->withCount([
                'attendances as present_count' => fn($q) => $q->present(),
                'attendances as total_count',
            ])->get();

        if ($meetings->isEmpty()) return 0;

        $rates = $meetings->map(fn($m) => $m->total_count > 0
            ? ($m->present_count / $m->total_count * 100) : 0);

        return round($rates->avg(), 1);
    }
}
