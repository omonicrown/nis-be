<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Meeting;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceUploadController extends Controller
{
    use ApiResponse;

    /**
     * Upload attendance via Excel file.
     *
     * Expected Excel format:
     * Column A: S/N
     * Column B: Name (Last name, First name or Full name)
     * Column C: SURCON Reg No (optional but improves matching)
     * Column D: NIS Membership ID (optional but improves matching)
     * Column E: Status (present / absent / apology)
     * Column F: Note (optional, e.g., apology reason)
     *
     * POST /api/admin/meetings/{meeting}/attendance/upload
     */
    public function upload(Request $request, Meeting $meeting): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, true);
        } catch (\Exception $e) {
            return $this->error('Failed to read file: ' . $e->getMessage(), 400);
        }

        // Detect header row — look for keywords
        $headerRowIndex = $this->detectHeaderRow($rows);
        if ($headerRowIndex) {
            unset($rows[$headerRowIndex]);
        }

        // Map columns
        $columnMap = $this->detectColumns($rows, $headerRowIndex ? $worksheet->toArray(null, true, true, true)[$headerRowIndex] : null);

        $stats = ['total' => 0, 'matched' => 0, 'unmatched' => 0, 'errors' => 0];
        $unmatched = [];
        $adminId = $request->user()->id;

        foreach ($rows as $rowIndex => $row) {
            $name = $this->cleanText($row[$columnMap['name']] ?? '');
            if (empty($name)) continue;

            $stats['total']++;

            $surcon = $this->cleanText($row[$columnMap['surcon']] ?? '');
            $nisId = $this->cleanText($row[$columnMap['nis_id']] ?? '');
            $status = $this->parseStatus($row[$columnMap['status']] ?? 'present');
            $note = $this->cleanText($row[$columnMap['note']] ?? '');

            // Try to match member
            $user = $this->findMember($name, $surcon, $nisId);

            if (!$user) {
                $stats['unmatched']++;
                $unmatched[] = [
                    'row'    => $rowIndex,
                    'name'   => $name,
                    'surcon' => $surcon,
                    'nis_id' => $nisId,
                    'status' => $status,
                ];
                continue;
            }

            try {
                Attendance::updateOrCreate(
                    [
                        'meeting_id' => $meeting->id,
                        'user_id'    => $user->id,
                    ],
                    [
                        'status'          => $status,
                        'check_in_method' => 'admin',
                        'checked_in_at'   => $status === 'present' ? now() : null,
                        'marked_by'       => $adminId,
                        'note'            => $note ?: null,
                    ]
                );
                $stats['matched']++;
            } catch (\Exception $e) {
                $stats['errors']++;
            }
        }

        // Summary counts
        $attendanceSummary = [
            'present' => $meeting->attendances()->where('status', 'present')->count(),
            'absent'  => $meeting->attendances()->where('status', 'absent')->count(),
            'apology' => $meeting->attendances()->where('status', 'apology')->count(),
            'total'   => $meeting->attendances()->count(),
        ];

        return $this->success([
            'stats'              => $stats,
            'unmatched_members'  => $unmatched,
            'attendance_summary' => $attendanceSummary,
        ], "{$stats['matched']} of {$stats['total']} attendance records uploaded. {$stats['unmatched']} could not be matched.");
    }

    /**
     * Download a blank attendance template for a meeting.
     *
     * GET /api/admin/meetings/{meeting}/attendance/template
     */
    public function downloadTemplate(Request $request, Meeting $meeting): JsonResponse
    {
        $members = User::active()
            ->with('membershipCategory')
            ->orderBy('last_name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance');

        // Meeting info header
        $sheet->setCellValue('A1', 'Meeting:');
        $sheet->setCellValue('B1', $meeting->title);
        $sheet->setCellValue('A2', 'Date:');
        $sheet->setCellValue('B2', $meeting->meeting_date->format('Y-m-d'));
        $sheet->setCellValue('A3', 'Venue:');
        $sheet->setCellValue('B3', $meeting->venue ?? 'NIS Plaza, Ikolaba');

        // Column headers
        $headers = ['S/N', 'NAME', 'SURCON REG NO', 'NIS MEMBERSHIP ID', 'CATEGORY', 'STATUS', 'NOTE'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $headerRow = 5;

        foreach ($headers as $i => $header) {
            $cell = $columns[$i] . $headerRow;
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B5E20');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Fill member data
        $rowNum = $headerRow + 1;
        foreach ($members as $i => $member) {
            $sheet->setCellValue("A{$rowNum}", $i + 1);
            $sheet->setCellValue("B{$rowNum}", $member->full_name);
            $sheet->setCellValue("C{$rowNum}", $member->surcon_reg_no);
            $sheet->setCellValue("D{$rowNum}", $member->nis_membership_id);
            $sheet->setCellValue("E{$rowNum}", $member->membershipCategory?->name);
            $sheet->setCellValue("F{$rowNum}", 'absent'); // Default: absent

            // Alternate row color
            if ($i % 2 === 1) {
                $sheet->getStyle("A{$rowNum}:G{$rowNum}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F5E9');
            }

            $rowNum++;
        }

        // Status dropdown validation
        $validation = $sheet->getCell("F" . ($headerRow + 1))->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setFormula1('"present,absent,apology"');
        $validation->setShowDropDown(true);

        // Apply validation to all status cells
        for ($r = $headerRow + 1; $r < $rowNum; $r++) {
            $sheet->getCell("F{$r}")->setDataValidation(clone $validation);
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(30);

        // Instructions sheet
        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->setCellValue('A1', 'ATTENDANCE UPLOAD INSTRUCTIONS');
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructions->setCellValue('A3', '1. The STATUS column accepts only: present, absent, apology');
        $instructions->setCellValue('A4', '2. Fill in the STATUS for each member who attended');
        $instructions->setCellValue('A5', '3. Add a NOTE for members who sent apologies (reason)');
        $instructions->setCellValue('A6', '4. Do NOT delete or reorder the SURCON/NIS ID columns — they are used for matching');
        $instructions->setCellValue('A7', '5. You can add new rows at the bottom for members not in the list');
        $instructions->setCellValue('A8', '6. Upload the file at: POST /admin/meetings/{id}/attendance/upload');
        $instructions->getColumnDimension('A')->setWidth(80);

        $spreadsheet->setActiveSheetIndex(0);

        // Save to temp and return URL
        $fileName = 'attendance_template_' . Str::slug($meeting->title) . '.xlsx';
        $filePath = storage_path("app/temp/{$fileName}");

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Return as downloadable response
        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Monthly attendance report — how many meetings each member attended.
     *
     * GET /api/admin/reports/attendance/monthly?year=2026
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        // Get all meetings in the year
        $meetings = Meeting::byYear($year)
            ->where('status', 'completed')
            ->orderBy('meeting_date')
            ->get(['id', 'title', 'meeting_date']);

        if ($meetings->isEmpty()) {
            return $this->success([
                'year'     => (int) $year,
                'meetings' => [],
                'members'  => [],
            ], 'No completed meetings found for this year.');
        }

        $meetingIds = $meetings->pluck('id');

        // Get all attendance records for the year
        $attendances = Attendance::whereIn('meeting_id', $meetingIds)
            ->with(['user.membershipCategory'])
            ->get();

        // Build member-level report
        $memberReport = [];
        $grouped = $attendances->groupBy('user_id');

        foreach ($grouped as $userId => $records) {
            $user = $records->first()->user;
            if (!$user) continue;

            $monthlyBreakdown = [];
            foreach ($meetings as $meeting) {
                $record = $records->where('meeting_id', $meeting->id)->first();
                $month = $meeting->meeting_date->format('M');
                $monthlyBreakdown[] = [
                    'meeting_id'   => $meeting->id,
                    'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
                    'month'        => $month,
                    'status'       => $record ? $record->status : 'absent',
                ];
            }

            $presentCount = $records->where('status', 'present')->count();
            $apologyCount = $records->where('status', 'apology')->count();
            $absentCount = $meetings->count() - $presentCount - $apologyCount;

            $memberReport[] = [
                'user_id'             => $userId,
                'full_name'           => $user->full_name,
                'surcon_reg_no'       => $user->surcon_reg_no,
                'nis_membership_id'   => $user->nis_membership_id,
                'membership_category' => $user->membershipCategory?->name,
                'designation'         => $user->membershipCategory?->designation,
                'total_meetings'      => $meetings->count(),
                'present_count'       => $presentCount,
                'absent_count'        => $absentCount,
                'apology_count'       => $apologyCount,
                'attendance_rate'     => round(($presentCount / $meetings->count()) * 100, 1),
                'monthly'             => $monthlyBreakdown,
            ];
        }

        // Also include members with NO attendance records (completely absent)
        $membersWithRecords = $grouped->keys()->toArray();
        $absentMembers = User::active()
            ->with('membershipCategory')
            ->whereNotIn('id', $membersWithRecords)
            ->get();

        foreach ($absentMembers as $user) {
            $monthlyBreakdown = $meetings->map(fn($m) => [
                'meeting_id'   => $m->id,
                'meeting_date' => $m->meeting_date->format('Y-m-d'),
                'month'        => $m->meeting_date->format('M'),
                'status'       => 'absent',
            ])->toArray();

            $memberReport[] = [
                'user_id'             => $user->id,
                'full_name'           => $user->full_name,
                'surcon_reg_no'       => $user->surcon_reg_no,
                'nis_membership_id'   => $user->nis_membership_id,
                'membership_category' => $user->membershipCategory?->name,
                'designation'         => $user->membershipCategory?->designation,
                'total_meetings'      => $meetings->count(),
                'present_count'       => 0,
                'absent_count'        => $meetings->count(),
                'apology_count'       => 0,
                'attendance_rate'     => 0,
                'monthly'             => $monthlyBreakdown,
            ];
        }

        // Sort by attendance rate descending
        usort($memberReport, fn($a, $b) => $b['attendance_rate'] <=> $a['attendance_rate']);

        // Summary stats
        $totalMembers = count($memberReport);
        $avgRate = $totalMembers > 0 ? round(collect($memberReport)->avg('attendance_rate'), 1) : 0;

        return $this->success([
            'year'           => (int) $year,
            'total_meetings' => $meetings->count(),
            'total_members'  => $totalMembers,
            'average_attendance_rate' => $avgRate,
            'meetings'       => $meetings->map(fn($m) => [
                'id'    => $m->id,
                'title' => $m->title,
                'date'  => $m->meeting_date->format('Y-m-d'),
                'month' => $m->meeting_date->format('M'),
            ]),
            'members' => $memberReport,
        ]);
    }

    /**
     * Export monthly attendance report as Excel.
     *
     * GET /api/admin/reports/attendance/monthly/export?year=2026
     */
    public function exportMonthlyReport(Request $request): mixed
    {
        $year = $request->get('year', date('Y'));

        $meetings = Meeting::byYear($year)
            ->where('status', 'completed')
            ->orderBy('meeting_date')
            ->get();

        $members = User::active()
            ->with('membershipCategory')
            ->orderBy('last_name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Attendance {$year}");

        // Title
        $sheet->setCellValue('A1', "NIS OYO STATE BRANCH — ATTENDANCE REPORT {$year}");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1B5E20');
        $sheet->mergeCells('A1:' . $this->colLetter(5 + $meetings->count()) . '1');

        // Headers
        $headerRow = 3;
        $fixedHeaders = ['S/N', 'NAME', 'SURCON NO', 'NIS ID', 'CATEGORY'];
        $col = 0;

        foreach ($fixedHeaders as $h) {
            $cell = $this->colLetter($col) . $headerRow;
            $sheet->setCellValue($cell, $h);
            $col++;
        }

        // Monthly headers
        foreach ($meetings as $meeting) {
            $cell = $this->colLetter($col) . $headerRow;
            $sheet->setCellValue($cell, $meeting->meeting_date->format('M d'));
            $col++;
        }

        // Summary headers
        $presentCol = $col;
        $sheet->setCellValue($this->colLetter($col) . $headerRow, 'PRESENT');
        $col++;
        $sheet->setCellValue($this->colLetter($col) . $headerRow, 'ABSENT');
        $col++;
        $sheet->setCellValue($this->colLetter($col) . $headerRow, 'APOLOGY');
        $col++;
        $sheet->setCellValue($this->colLetter($col) . $headerRow, 'RATE (%)');
        $totalCols = $col;

        // Style headers
        for ($c = 0; $c <= $totalCols; $c++) {
            $cell = $this->colLetter($c) . $headerRow;
            $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B5E20');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        }

        // Member rows
        $rowNum = $headerRow + 1;
        $meetingIds = $meetings->pluck('id');
        $allAttendances = Attendance::whereIn('meeting_id', $meetingIds)->get()->groupBy('user_id');

        foreach ($members as $i => $member) {
            $sheet->setCellValue("A{$rowNum}", $i + 1);
            $sheet->setCellValue("B{$rowNum}", $member->full_name);
            $sheet->setCellValue("C{$rowNum}", $member->surcon_reg_no);
            $sheet->setCellValue("D{$rowNum}", $member->nis_membership_id);
            $sheet->setCellValue("E{$rowNum}", $member->membershipCategory?->name);

            $memberAttendances = $allAttendances->get($member->id, collect());
            $presentCount = 0;
            $apologyCount = 0;

            $col = 5; // Start after fixed columns
            foreach ($meetings as $meeting) {
                $record = $memberAttendances->where('meeting_id', $meeting->id)->first();
                $status = $record ? strtoupper(substr($record->status, 0, 1)) : 'A'; // P, A, or AP

                if ($record && $record->status === 'present') {
                    $status = 'P';
                    $presentCount++;
                } elseif ($record && $record->status === 'apology') {
                    $status = 'AP';
                    $apologyCount++;
                } else {
                    $status = 'A';
                }

                $cell = $this->colLetter($col) . $rowNum;
                $sheet->setCellValue($cell, $status);
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Color code
                if ($status === 'P') {
                    $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8E6C9');
                    $sheet->getStyle($cell)->getFont()->getColor()->setRGB('2E7D32');
                } elseif ($status === 'AP') {
                    $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3E0');
                    $sheet->getStyle($cell)->getFont()->getColor()->setRGB('F57C00');
                } else {
                    $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEBEE');
                    $sheet->getStyle($cell)->getFont()->getColor()->setRGB('D32F2F');
                }

                $col++;
            }

            $absentCount = $meetings->count() - $presentCount - $apologyCount;
            $rate = $meetings->count() > 0 ? round(($presentCount / $meetings->count()) * 100, 1) : 0;

            $sheet->setCellValue($this->colLetter($col) . $rowNum, $presentCount);
            $col++;
            $sheet->setCellValue($this->colLetter($col) . $rowNum, $absentCount);
            $col++;
            $sheet->setCellValue($this->colLetter($col) . $rowNum, $apologyCount);
            $col++;
            $sheet->setCellValue($this->colLetter($col) . $rowNum, $rate . '%');

            // Alternate row background
            if ($i % 2 === 1) {
                $range = "A{$rowNum}:" . $this->colLetter($totalCols) . $rowNum;
                // Only apply to cells without existing fill
                for ($fc = 0; $fc < 5; $fc++) {
                    $sheet->getStyle($this->colLetter($fc) . $rowNum)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
                }
            }

            $rowNum++;
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(14);
        for ($c = 5; $c <= $totalCols; $c++) {
            $sheet->getColumnDimension($this->colLetter($c))->setWidth(10);
        }

        // Freeze panes
        $sheet->freezePane('F4');

        // Save and return
        $fileName = "NIS_Attendance_Report_{$year}.xlsx";
        $filePath = storage_path("app/temp/{$fileName}");

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Get single member's attendance across all meetings.
     *
     * GET /api/admin/members/{user}/attendance?year=2026
     */
    public function memberAttendance(Request $request, User $user): JsonResponse
    {
        $year = $request->get('year', date('Y'));

        $meetings = Meeting::byYear($year)
            ->where('status', 'completed')
            ->orderBy('meeting_date')
            ->get();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereIn('meeting_id', $meetings->pluck('id'))
            ->get()
            ->keyBy('meeting_id');

        $records = $meetings->map(function ($meeting) use ($attendances) {
            $record = $attendances->get($meeting->id);
            return [
                'meeting_id'   => $meeting->id,
                'title'        => $meeting->title,
                'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
                'month'        => $meeting->meeting_date->format('M Y'),
                'status'       => $record ? $record->status : 'absent',
                'check_in_method' => $record?->check_in_method,
                'note'         => $record?->note,
            ];
        });

        $presentCount = $records->where('status', 'present')->count();
        $apologyCount = $records->where('status', 'apology')->count();
        $totalMeetings = $meetings->count();

        return $this->success([
            'member' => [
                'id'                  => $user->id,
                'full_name'           => $user->full_name,
                'surcon_reg_no'       => $user->surcon_reg_no,
                'nis_membership_id'   => $user->nis_membership_id,
                'membership_category' => $user->membershipCategory?->name,
            ],
            'year'            => (int) $year,
            'total_meetings'  => $totalMeetings,
            'present_count'   => $presentCount,
            'absent_count'    => $totalMeetings - $presentCount - $apologyCount,
            'apology_count'   => $apologyCount,
            'attendance_rate' => $totalMeetings > 0 ? round(($presentCount / $totalMeetings) * 100, 1) : 0,
            'records'         => $records,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Find member by SURCON, NIS ID, or name fuzzy match.
     */
    private function findMember(string $name, ?string $surcon, ?string $nisId): ?User
    {
        // Priority 1: SURCON Reg No (most unique)
        if ($surcon) {
            $cleanSurcon = preg_replace('/\s+/', '', $surcon);
            $user = User::where('surcon_reg_no', $cleanSurcon)->first();
            if ($user) return $user;
        }

        // Priority 2: NIS Membership ID
        if ($nisId) {
            $cleanNis = preg_replace('/\s+/', '', $nisId);
            $user = User::where('nis_membership_id', $cleanNis)->first();
            if ($user) return $user;
        }

        // Priority 3: Name match
        $name = preg_replace('/\s+/', ' ', trim($name));
        $nameParts = preg_split('/[\s,]+/', strtolower($name));

        if (count($nameParts) >= 2) {
            // Try last_name + first_name
            $user = User::whereRaw('LOWER(last_name) = ?', [$nameParts[0]])
                ->whereRaw('LOWER(first_name) = ?', [$nameParts[1]])
                ->first();
            if ($user) return $user;

            // Try first_name + last_name (reversed)
            $user = User::whereRaw('LOWER(first_name) = ?', [$nameParts[0]])
                ->whereRaw('LOWER(last_name) = ?', [$nameParts[1]])
                ->first();
            if ($user) return $user;
        }

        // Fallback: broader LIKE search
        if (count($nameParts) >= 1) {
            $user = User::where(function ($q) use ($nameParts) {
                foreach ($nameParts as $part) {
                    if (strlen($part) > 2) {
                        $q->where(function ($sq) use ($part) {
                            $sq->where('last_name', 'like', "%{$part}%")
                                ->orWhere('first_name', 'like', "%{$part}%");
                        });
                    }
                }
            })->first();
            if ($user) return $user;
        }

        return null;
    }

    /**
     * Parse status string to valid enum value.
     */
    private function parseStatus(?string $status): string
    {
        if (!$status) return 'present';

        $status = strtolower(trim($status));

        return match (true) {
            in_array($status, ['p', 'present', 'yes', '1', 'attended'])     => 'present',
            in_array($status, ['ap', 'apology', 'excused', 'sorry'])        => 'apology',
            in_array($status, ['a', 'absent', 'no', '0', 'missing', ''])    => 'absent',
            default                                                           => 'absent',
        };
    }

    /**
     * Detect header row by looking for keywords.
     */
    private function detectHeaderRow(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $combined = strtolower(implode(' ', array_filter(array_map('strval', $row))));
            if (str_contains($combined, 'name') && (str_contains($combined, 'status') || str_contains($combined, 'surcon'))) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Detect which columns contain which data.
     */
    private function detectColumns(array $rows, ?array $headerRow): array
    {
        // Default mapping (matches our template)
        $map = ['name' => 'B', 'surcon' => 'C', 'nis_id' => 'D', 'status' => 'F', 'note' => 'G'];

        if ($headerRow) {
            foreach ($headerRow as $col => $header) {
                $h = strtolower(trim($header ?? ''));
                if (str_contains($h, 'name') && !str_contains($h, 'file'))           $map['name'] = $col;
                elseif (str_contains($h, 'surcon'))                                     $map['surcon'] = $col;
                elseif (str_contains($h, 'nis') || str_contains($h, 'membership id'))  $map['nis_id'] = $col;
                elseif (str_contains($h, 'status'))                                     $map['status'] = $col;
                elseif (str_contains($h, 'note') || str_contains($h, 'remark'))         $map['note'] = $col;
            }
        }

        return $map;
    }

    private function cleanText(?string $text): ?string
    {
        if (!$text) return null;
        return trim(preg_replace('/\s+/', ' ', $text)) ?: null;
    }

    private function colLetter(int $col): string
    {
        $letter = '';
        while ($col >= 0) {
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intdiv($col, 26) - 1;
        }
        return $letter;
    }
}
