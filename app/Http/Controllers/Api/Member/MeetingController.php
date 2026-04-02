<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\MeetingResource;
use App\Models\Attendance;
use App\Models\Meeting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    use ApiResponse;

    /**
     * List meetings the member can see.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Meeting::with('attendances')
            ->when($request->type, fn($q, $type) => $q->byType($type))
            ->when($request->year, fn($q, $year) => $q->byYear($year))
            ->when($request->upcoming, fn($q) => $q->upcoming())
            ->when($request->search, fn($q, $s) => $q->search($s))
            ->whereIn('status', ['scheduled', 'ongoing', 'completed'])
            ->orderBy('meeting_date', 'desc');

        $meetings = $query->paginate($request->per_page ?? 15);

        return $this->paginated($meetings->through(fn($m) => new MeetingResource($m)));
    }

    /**
     * View a single meeting.
     */
    public function show(Meeting $meeting): JsonResponse
    {
        $meeting->load('attendances');

        $myAttendance = $meeting->attendances
            ->where('user_id', request()->user()->id)
            ->first();

        $data = (new MeetingResource($meeting))->toArray(request());
        $data['my_attendance'] = $myAttendance ? [
            'status'         => $myAttendance->status,
            'checked_in_at'  => $myAttendance->checked_in_at?->toIso8601String(),
            'check_in_method' => $myAttendance->check_in_method,
        ] : null;

        return $this->success($data);
    }

    /**
     * QR code check-in for a meeting.
     * Member scans QR → frontend sends the qr_code value here.
     */
    public function qrCheckIn(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        $meeting = Meeting::where('qr_code', $request->qr_code)
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->first();

        if (!$meeting) {
            return $this->error('Invalid or expired QR code. No active meeting found.', 404);
        }

        $user = $request->user();

        // Check if already checked in
        $existing = Attendance::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->status === 'present') {
            return $this->error('You have already checked in to this meeting.', 409);
        }

        $attendance = Attendance::updateOrCreate(
            [
                'meeting_id' => $meeting->id,
                'user_id'    => $user->id,
            ],
            [
                'status'          => 'present',
                'check_in_method' => 'qr_code',
                'checked_in_at'   => now(),
            ]
        );

        $attendance->load('user.membershipCategory');

        return $this->success([
            'meeting'    => $meeting->title,
            'attendance' => new AttendanceResource($attendance),
        ], 'Checked in successfully!');
    }

    /**
     * Submit apology for a meeting.
     */
    public function submitApology(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        // Can only apologize for upcoming/ongoing meetings
        if (!in_array($meeting->status, ['scheduled', 'ongoing'])) {
            return $this->error('You can only submit an apology for upcoming or ongoing meetings.');
        }

        $existing = Attendance::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->status === 'present') {
            return $this->error('You are already marked as present.');
        }

        $attendance = Attendance::updateOrCreate(
            [
                'meeting_id' => $meeting->id,
                'user_id'    => $user->id,
            ],
            [
                'status' => 'apology',
                'note'   => $request->note,
            ]
        );

        return $this->success(null, 'Apology submitted successfully.');
    }

    /**
     * Get my attendance history across meetings.
     */
    public function myAttendance(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Attendance::with(['meeting'])
            ->where('user_id', $user->id)
            ->when($request->year, fn($q, $y) => $q->whereHas('meeting', fn($mq) => $mq->byYear($y)))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc(
                Meeting::select('meeting_date')
                    ->whereColumn('meetings.id', 'attendances.meeting_id')
            );

        $records = $query->paginate($request->per_page ?? 20);

        // Attendance summary stats
        $allRecords = Attendance::where('user_id', $user->id);
        if ($request->year) {
            $allRecords->whereHas('meeting', fn($q) => $q->byYear($request->year));
        }
        $stats = [
            'total_meetings' => (clone $allRecords)->count(),
            'present'        => (clone $allRecords)->present()->count(),
            'absent'         => (clone $allRecords)->absent()->count(),
            'apology'        => (clone $allRecords)->apology()->count(),
        ];
        $stats['attendance_rate'] = $stats['total_meetings'] > 0
            ? round(($stats['present'] / $stats['total_meetings']) * 100, 1)
            : 0;

        $transformed = $records->through(fn($a) => [
            'id'              => $a->id,
            'meeting'         => [
                'id'           => $a->meeting->id,
                'title'        => $a->meeting->title,
                'meeting_date' => $a->meeting->meeting_date->format('Y-m-d'),
                'type'         => $a->meeting->type,
            ],
            'status'          => $a->status,
            'check_in_method' => $a->check_in_method,
            'checked_in_at'   => $a->checked_in_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $transformed->items(),
            'stats'   => $stats,
            'meta'    => [
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
                'per_page'     => $records->perPage(),
                'total'        => $records->total(),
            ],
        ]);
    }
}
