<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkAttendanceRequest;
use App\Http\Requests\Admin\MeetingRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\MeetingResource;
use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\User;
use App\Services\CloudinaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MeetingController extends Controller
{
    use ApiResponse;

    private CloudinaryService $cloudinary;

    public function __construct(CloudinaryService $cloudinary)
    {
        $this->cloudinary = $cloudinary;
    }

    /**
     * List all meetings with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Meeting::with(['creator', 'attendances'])
            ->when($request->type, fn($q, $type) => $q->byType($type))
            ->when($request->status, fn($q, $status) => $q->byStatus($status))
            ->when($request->year, fn($q, $year) => $q->byYear($year))
            ->when($request->month, fn($q, $month) => $q->byMonth($month))
            ->when($request->search, fn($q, $search) => $q->search($search))
            ->when($request->upcoming, fn($q) => $q->upcoming())
            ->orderBy($request->sort_by ?? 'meeting_date', $request->sort_dir ?? 'desc');

        $meetings = $query->paginate($request->per_page ?? 15);

        return $this->paginated($meetings->through(fn($m) => new MeetingResource($m)));
    }

    /**
     * Create a new meeting.
     */
    public function store(MeetingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->id;
        $validated['qr_code'] = Str::uuid()->toString();

        $meeting = Meeting::create($validated);
        $meeting->load(['creator', 'attendances']);

        return $this->created(new MeetingResource($meeting), 'Meeting created successfully.');
    }

    /**
     * View a single meeting with full details.
     */
    public function show(Meeting $meeting): JsonResponse
    {
        $meeting->load(['creator', 'attendances.user.membershipCategory']);

        return $this->success(new MeetingResource($meeting));
    }

    /**
     * Update a meeting.
     */
    public function update(MeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $meeting->update($request->validated());
        $meeting->load(['creator', 'attendances']);

        return $this->success(new MeetingResource($meeting), 'Meeting updated successfully.');
    }

    /**
     * Delete a meeting.
     */
    public function destroy(Meeting $meeting): JsonResponse
    {
        // Delete minutes file from Cloudinary if exists
        if ($meeting->minutes_file_public_id) {
            $this->cloudinary->deleteDocument($meeting->minutes_file_public_id);
        }

        $meeting->delete();

        return $this->success(null, 'Meeting deleted successfully.');
    }

    /**
     * Update meeting status.
     */
    public function updateStatus(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:scheduled,ongoing,completed,cancelled'],
        ]);

        $meeting->update(['status' => $request->status]);

        return $this->success(new MeetingResource($meeting), 'Meeting status updated.');
    }

    /**
     * Upload meeting minutes (PDF/DOC).
     */
    public function uploadMinutes(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'minutes_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'], // 10MB
            'minutes_text'  => ['nullable', 'string'],
        ]);

        // Delete old file if exists
        if ($meeting->minutes_file_public_id) {
            $this->cloudinary->deleteDocument($meeting->minutes_file_public_id);
        }

        $result = $this->cloudinary->uploadDocument($request->file('minutes_file'), 'minutes');

        $meeting->update([
            'minutes_file_url'       => $result['secure_url'],
            'minutes_file_public_id' => $result['public_id'],
            'minutes_text'           => $request->minutes_text ?? $meeting->minutes_text,
        ]);

        return $this->success([
            'minutes_file_url' => $result['secure_url'],
            'original_name'    => $result['original_name'],
        ], 'Minutes uploaded successfully.');
    }

    /**
     * Update minutes text content.
     */
    public function updateMinutesText(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'minutes_text' => ['required', 'string'],
        ]);

        $meeting->update(['minutes_text' => $request->minutes_text]);

        return $this->success(null, 'Minutes text updated.');
    }

    /**
     * Delete minutes file.
     */
    public function deleteMinutes(Meeting $meeting): JsonResponse
    {
        if ($meeting->minutes_file_public_id) {
            $this->cloudinary->deleteDocument($meeting->minutes_file_public_id);
        }

        $meeting->update([
            'minutes_file_url'       => null,
            'minutes_file_public_id' => null,
        ]);

        return $this->success(null, 'Minutes file deleted.');
    }

    // ─── Attendance Management ──────────────────────────────

    /**
     * Get attendance list for a meeting.
     */
    public function attendance(Request $request, Meeting $meeting): JsonResponse
    {
        $query = $meeting->attendances()
            ->with(['user.membershipCategory', 'markedBy'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('status');

        $attendances = $query->get();

        return $this->success([
            'meeting'    => new MeetingResource($meeting),
            'attendances' => AttendanceResource::collection($attendances),
            'summary'     => [
                'present' => $attendances->where('status', 'present')->count(),
                'absent'  => $attendances->where('status', 'absent')->count(),
                'apology' => $attendances->where('status', 'apology')->count(),
                'total'   => $attendances->count(),
            ],
        ]);
    }

    /**
     * Mark single member attendance.
     */
    public function markAttendance(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'status'  => ['required', 'in:present,absent,apology'],
            'note'    => ['nullable', 'string', 'max:500'],
        ]);

        $attendance = Attendance::updateOrCreate(
            [
                'meeting_id' => $meeting->id,
                'user_id'    => $request->user_id,
            ],
            [
                'status'          => $request->status,
                'check_in_method' => 'admin',
                'checked_in_at'   => $request->status === 'present' ? now() : null,
                'marked_by'       => $request->user()->id,
                'note'            => $request->note,
            ]
        );

        $attendance->load(['user.membershipCategory', 'markedBy']);

        return $this->success(new AttendanceResource($attendance), 'Attendance marked.');
    }

    /**
     * Bulk mark attendance for multiple members.
     */
    public function bulkMarkAttendance(BulkAttendanceRequest $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validated();
        $count = 0;

        foreach ($validated['attendances'] as $record) {
            Attendance::updateOrCreate(
                [
                    'meeting_id' => $meeting->id,
                    'user_id'    => $record['user_id'],
                ],
                [
                    'status'          => $record['status'],
                    'check_in_method' => 'admin',
                    'checked_in_at'   => $record['status'] === 'present' ? now() : null,
                    'marked_by'       => $request->user()->id,
                    'note'            => $record['note'] ?? null,
                ]
            );
            $count++;
        }

        return $this->success(
            ['marked_count' => $count],
            "{$count} attendance record(s) saved."
        );
    }

    /**
     * Initialize attendance for all active members (set everyone as absent by default).
     */
    public function initializeAttendance(Meeting $meeting): JsonResponse
    {
        $activeMembers = User::active()->pluck('id');
        $existing = $meeting->attendances()->pluck('user_id');
        $missing = $activeMembers->diff($existing);

        $records = $missing->map(fn($userId) => [
            'meeting_id' => $meeting->id,
            'user_id'    => $userId,
            'status'     => 'absent',
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        Attendance::insert($records);

        return $this->success(
            ['initialized_count' => count($records)],
            count($records) . ' member(s) initialized as absent.'
        );
    }

    /**
     * Regenerate QR code for a meeting.
     */
    public function regenerateQrCode(Meeting $meeting): JsonResponse
    {
        $meeting->update([
            'qr_code' => Str::uuid()->toString(),
        ]);

        return $this->success([
            'qr_code' => $meeting->qr_code,
        ], 'QR code regenerated.');
    }
}
