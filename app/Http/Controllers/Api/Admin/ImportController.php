<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Models\MembershipCategory;
use App\Models\Role;
use App\Models\Subgroup;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ImportController extends Controller
{
    use ApiResponse;

    private array $stats = [];
    private array $errorLog = [];

    /**
     * Import members from NIS-EXISTING sheet.
     *
     * GET /api/admin/import/existing?file=WEB.xlsx&dry_run=1
     */
    public function importExisting(Request $request): JsonResponse
    {
        $this->resetStats();

        $fileName = $request->get('file', 'WEB.xlsx');
        $dryRun = $request->boolean('dry_run', false);
        $defaultPassword = $request->get('password', 'NISMember@2026');
        $sheetName = $request->get('sheet', 'NIS-EXISTING');

        $filePath = storage_path("app/imports/{$fileName}");

        if (!file_exists($filePath)) {
            return $this->error("File not found: storage/app/imports/{$fileName}. Upload the Excel file to storage/app/imports/ first.", 404);
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getSheetByName($sheetName);

            if (!$worksheet) {
                return $this->error("Sheet '{$sheetName}' not found. Available: " . implode(', ', $spreadsheet->getSheetNames()));
            }
        } catch (\Exception $e) {
            return $this->error("Failed to load spreadsheet: {$e->getMessage()}", 500);
        }

        $categories = MembershipCategory::pluck('id', 'slug')->toArray();
        $memberRole = Role::where('slug', 'member')->first();
        $subgroups = Subgroup::pluck('id', 'slug')->toArray();

        if (!$memberRole) {
            return $this->error('Member role not found. Run db:seed first.');
        }

        $rows = $worksheet->toArray(null, true, true, true);
        array_shift($rows); // Remove header
        $this->stats['total'] = count($rows);

        /**
         * NIS-EXISTING columns:
         * A=S/N, B=NAMES, C=GENDER, D=SURCON REG. NO.,
         * E=Member NIS ID, F=(blank), G=OFFICE ADDRESS,
         * H=RESIDENTIAL ADDRESS, I=PHONE NO, J=E-MAIL,
         * K=SURFIX, L=SUBGROUP
         */
        foreach ($rows as $rowIndex => $row) {
            $actualRow = $rowIndex + 2;

            try {
                $name = trim($row['B'] ?? '');
                if (empty($name)) {
                    $this->stats['skipped']++;
                    continue;
                }

                $nameParts = $this->parseName($name);
                $gender = $this->normalizeGender($row['C'] ?? '');
                $surcon = $this->cleanSurcon($row['D'] ?? '');
                $rawNisId = trim($row['E'] ?? '');
                $officeAddress = $this->cleanText($row['G'] ?? '');
                $residentialAddress = $this->cleanText($row['H'] ?? '');
                $phone = $this->cleanPhone($row['I'] ?? '');
                $email = $this->cleanEmail($row['J'] ?? '');
                $suffix = $this->cleanText($row['K'] ?? '');
                $subgroupSlugs = $this->detectSubgroups($row['L'] ?? '');

                // Check duplicates
                if ($email && User::where('email', $email)->exists()) {
                    $this->logError($actualRow, "Duplicate email: {$email}");
                    $this->stats['skipped']++;
                    continue;
                }

                if ($surcon && User::where('surcon_reg_no', $surcon)->exists()) {
                    $this->logError($actualRow, "Duplicate SURCON: {$surcon}");
                    $this->stats['skipped']++;
                    continue;
                }

                $categoryId = $this->detectCategory($rawNisId, $categories);
                $nisId = $this->extractNisId($rawNisId);

                if (!$dryRun) {
                    DB::transaction(function () use (
                        $nameParts, $email, $phone, $gender, $surcon, $nisId,
                        $suffix, $categoryId, $memberRole, $defaultPassword,
                        $officeAddress, $residentialAddress, $subgroupSlugs, $subgroups
                    ) {
                        $finalEmail = $email ?: $this->generatePlaceholderEmail($nameParts['first_name'], $nameParts['last_name']);

                        $user = User::create([
                            'first_name'             => $nameParts['first_name'],
                            'last_name'              => $nameParts['last_name'],
                            'other_names'            => $nameParts['other_names'],
                            'email'                  => $finalEmail,
                            'phone'                  => $phone,
                            'gender'                 => $gender,
                            'surcon_reg_no'          => $surcon,
                            'nis_membership_id'      => $nisId,
                            'suffix'                 => $suffix,
                            'membership_category_id' => $categoryId,
                            'role_id'                => $memberRole->id,
                            'status'                 => UserStatus::ACTIVE,
                            'password'               => Hash::make($defaultPassword),
                            'is_migrated'            => true,
                            'profile_completed'      => false,
                            'email_verified_at'      => now(),
                            'approved_at'            => now(),
                        ]);

                        MemberProfile::create([
                            'user_id'             => $user->id,
                            'office_address'      => $officeAddress,
                            'residential_address' => $residentialAddress,
                        ]);

                        if (!empty($subgroupSlugs)) {
                            $subgroupIds = collect($subgroupSlugs)
                                ->map(fn($slug) => $subgroups[$slug] ?? null)
                                ->filter()->toArray();
                            if (!empty($subgroupIds)) {
                                $user->subgroups()->attach($subgroupIds);
                            }
                        }
                    });
                }

                $this->stats['imported']++;

            } catch (\Exception $e) {
                $this->logError($actualRow, $e->getMessage());
                $this->stats['errors']++;
            }
        }

        return $this->success([
            'dry_run' => $dryRun,
            'stats'   => $this->stats,
            'errors'  => array_slice($this->errorLog, 0, 50),
        ], $dryRun ? 'Dry run complete. No data saved.' : 'Import complete.');
    }

    /**
     * Import/update members from NIS-NEW sheet.
     *
     * GET /api/admin/import/new?file=WEB.xlsx&dry_run=1
     */
    public function importNew(Request $request): JsonResponse
    {
        $this->stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $this->errorLog = [];

        $fileName = $request->get('file', 'WEB.xlsx');
        $dryRun = $request->boolean('dry_run', false);
        $defaultPassword = $request->get('password', 'NISMember@2026');

        $filePath = storage_path("app/imports/{$fileName}");

        if (!file_exists($filePath)) {
            return $this->error("File not found: storage/app/imports/{$fileName}", 404);
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getSheetByName('NIS-NEW');

            if (!$worksheet) {
                return $this->error("Sheet 'NIS-NEW' not found.");
            }
        } catch (\Exception $e) {
            return $this->error("Failed to load spreadsheet: {$e->getMessage()}", 500);
        }

        $categories = MembershipCategory::pluck('id', 'slug')->toArray();
        $memberRole = Role::where('slug', 'member')->first();
        $subgroups = Subgroup::pluck('id', 'slug')->toArray();

        $rows = $worksheet->toArray(null, true, true, true);
        array_shift($rows);
        $this->stats['total'] = count($rows);

        /**
         * NIS-NEW columns:
         * A=(empty), B=NAMES, C=GENDER, D=NIS MEMBERSHIP NUMBER,
         * E=SURCON REG. NO., F=DAY & MONTH OF BIRTH,
         * G=SUB GROUP, H=OFFICE ADDRESS, I=RESIDENTIAL ADDRESS,
         * J=PHONE NO, K=E-MAIL, L=SURFIX
         */
        foreach ($rows as $row) {
            $name = trim($row['B'] ?? '');
            if (empty($name)) {
                $this->stats['skipped']++;
                continue;
            }

            try {
                $surcon = preg_replace('/\s+/', '', trim($row['E'] ?? ''));
                $email = $this->cleanEmail($row['K'] ?? '');
                $phone = $this->cleanPhone($row['J'] ?? '');
                $nisId = $this->extractNisId($row['D'] ?? '');
                $dob = $this->parseDob($row['F'] ?? null);
                $subgroupSlugs = $this->parseSubgroupsFromNew($row['G'] ?? '');
                $subgroupIds = collect($subgroupSlugs)
                    ->map(fn($slug) => $subgroups[$slug] ?? null)
                    ->filter()->toArray();
                $nameParts = $this->parseName($name);

                // Find existing
                $existing = null;
                if ($surcon) $existing = User::where('surcon_reg_no', $surcon)->first();
                if (!$existing && $email) $existing = User::where('email', $email)->first();

                if ($existing && !$dryRun) {
                    DB::transaction(function () use ($existing, $row, $dob, $subgroupIds) {
                        $profile = $existing->profile;
                        if ($profile) {
                            $updates = [];
                            if (!$profile->date_of_birth && $dob) $updates['date_of_birth'] = $dob;
                            if (!$profile->office_address && $row['H']) $updates['office_address'] = trim($row['H']);
                            if (!$profile->residential_address && $row['I']) $updates['residential_address'] = trim($row['I']);
                            if (!empty($updates)) $profile->update($updates);
                        }
                        if (!empty($subgroupIds)) {
                            $existing->subgroups()->syncWithoutDetaching($subgroupIds);
                        }
                    });
                    $this->stats['updated']++;

                } elseif (!$existing && !$dryRun) {
                    DB::transaction(function () use (
                        $nameParts, $email, $phone, $surcon, $nisId, $row,
                        $dob, $categories, $memberRole, $subgroupIds, $defaultPassword
                    ) {
                        $finalEmail = $email ?: $this->generatePlaceholderEmail($nameParts['first_name'], $nameParts['last_name']);

                        $user = User::create([
                            'first_name'             => $nameParts['first_name'],
                            'last_name'              => $nameParts['last_name'],
                            'other_names'            => $nameParts['other_names'],
                            'email'                  => $finalEmail,
                            'phone'                  => $phone,
                            'gender'                 => $this->normalizeGender($row['C'] ?? ''),
                            'surcon_reg_no'          => $surcon,
                            'nis_membership_id'      => $nisId,
                            'suffix'                 => $this->cleanText($row['L'] ?? ''),
                            'membership_category_id' => $categories['member'] ?? null,
                            'role_id'                => $memberRole->id,
                            'status'                 => UserStatus::ACTIVE,
                            'password'               => Hash::make($defaultPassword),
                            'is_migrated'            => true,
                            'profile_completed'      => false,
                            'email_verified_at'      => now(),
                            'approved_at'            => now(),
                        ]);

                        MemberProfile::create([
                            'user_id'             => $user->id,
                            'office_address'      => $this->cleanText($row['H'] ?? ''),
                            'residential_address' => $this->cleanText($row['I'] ?? ''),
                            'date_of_birth'       => $dob,
                        ]);

                        if (!empty($subgroupIds)) {
                            $user->subgroups()->attach($subgroupIds);
                        }
                    });
                    $this->stats['created']++;

                } else {
                    $this->stats[$existing ? 'updated' : 'created']++;
                }

            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->logError(0, $e->getMessage());
            }
        }

        return $this->success([
            'dry_run' => $dryRun,
            'stats'   => $this->stats,
            'errors'  => array_slice($this->errorLog, 0, 50),
        ], $dryRun ? 'Dry run complete. No data saved.' : 'Import complete.');
    }

    /**
     * Export members as JSON (downloadable).
     *
     * GET /api/admin/export/members?status=active&category=fellow
     */
    public function exportMembers(Request $request): JsonResponse
    {
        $query = User::with(['membershipCategory', 'profile', 'subgroups']);

        $status = $request->get('status', 'active');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($category = $request->get('category')) {
            $query->whereHas('membershipCategory', fn($q) => $q->where('slug', $category));
        }

        $members = $query->orderBy('last_name')->get()->map(function ($member, $i) {
            $profile = $member->profile;
            return [
                'sn'                  => $i + 1,
                'last_name'           => $member->last_name,
                'first_name'          => $member->first_name,
                'other_names'         => $member->other_names,
                'gender'              => $member->gender,
                'email'               => $member->email,
                'phone'               => $member->phone,
                'surcon_reg_no'       => $member->surcon_reg_no,
                'nis_membership_id'   => $member->nis_membership_id,
                'membership_category' => $member->membershipCategory?->name,
                'designation'         => $member->membershipCategory?->designation,
                'status'              => $member->status?->value,
                'office_address'      => $profile?->office_address,
                'residential_address' => $profile?->residential_address,
                'date_of_birth'       => $profile?->date_of_birth?->format('Y-m-d'),
                'subgroups'           => $member->subgroups->pluck('name')->implode(', '),
                'registered_at'       => $member->created_at?->format('Y-m-d'),
            ];
        });

        return $this->success([
            'count'   => $members->count(),
            'members' => $members,
        ]);
    }

    // ─── Helper Methods ─────────────────────────────────────────────

    private function resetStats(): void
    {
        $this->stats = ['total' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0];
        $this->errorLog = [];
    }

    private function parseName(string $name): array
    {
        $name = preg_replace('/\s+/', ' ', trim($name));

        if (str_contains($name, ',')) {
            $parts = explode(',', $name, 2);
            $rest = explode(' ', trim($parts[1] ?? ''));
            return [
                'last_name'   => Str::title(trim($parts[0])),
                'first_name'  => Str::title($rest[0] ?? ''),
                'other_names' => count($rest) > 1 ? Str::title(implode(' ', array_slice($rest, 1))) : null,
            ];
        }

        $parts = explode(' ', $name);
        return [
            'last_name'   => Str::title($parts[0] ?? ''),
            'first_name'  => Str::title($parts[1] ?? ''),
            'other_names' => count($parts) > 2 ? Str::title(implode(' ', array_slice($parts, 2))) : null,
        ];
    }

    private function normalizeGender(?string $gender): ?string
    {
        if (!$gender) return null;
        $gender = strtolower(trim($gender));
        return match (true) {
            in_array($gender, ['m', 'male'])   => 'male',
            in_array($gender, ['f', 'female']) => 'female',
            default                             => null,
        };
    }

    private function cleanSurcon(?string $surcon): ?string
    {
        if (!$surcon) return null;
        $surcon = trim($surcon);
        if (preg_match('/^[a-zA-Z\s\.]+$/', $surcon)) return null;
        return preg_replace('/\s+/', '', $surcon);
    }

    private function detectCategory(?string $rawNisId, array $categories): ?int
    {
        if (!$rawNisId) return $categories['member'] ?? null;
        $lower = strtolower($rawNisId);

        if (str_contains($lower, 'fellow') || str_contains($lower, 'felow')) return $categories['fellow'] ?? null;
        if (str_contains($lower, 'associate') || str_contains($lower, 'asocciate') || $lower === 'anis') return $categories['associate'] ?? null;
        if (str_contains($lower, 'student')) return $categories['student'] ?? null;
        if (str_contains($lower, 'pupil') || str_contains($lower, 'probationer')) return $categories['probationer'] ?? null;
        if (str_contains($lower, 'technician') || str_contains($lower, 'technologist') || str_contains($lower, 'survey tech')) return $categories['probationer'] ?? null;

        if (preg_match('/nis\s*\/\s*f\s*\//', $lower)) return $categories['fellow'] ?? null;
        if (preg_match('/nis\s*\/\s*ass\s*\//', $lower)) return $categories['associate'] ?? null;
        if (preg_match('/nis\s*\/\s*fm\s*\//', $lower)) return $categories['member'] ?? null;

        if (str_contains($lower, 'mnis') || str_contains($lower, 'member') || str_contains($lower, 'fullmember') || str_contains($lower, 'full mumber') || str_contains($lower, 'memeber')) return $categories['member'] ?? null;

        return $categories['member'] ?? null;
    }

    private function extractNisId(?string $rawNisId): ?string
    {
        if (!$rawNisId) return null;
        if (preg_match('/(NIS\s*\/\s*(?:FM|F|ASS)\s*\/\s*[\d]+)/i', $rawNisId, $m)) return preg_replace('/\s+/', '', $m[1]);
        if (preg_match('/^(FM\s*\/\s*[\d]+)/i', $rawNisId, $m)) return 'NIS/' . preg_replace('/\s+/', '', $m[1]);
        if (stripos($rawNisId, 'NIS') === 0) return preg_replace('/\s+/', '', $rawNisId);
        if (preg_match('/^[a-zA-Z\s]+$/', trim($rawNisId))) return null;
        return null;
    }

    private function cleanPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $parts = preg_split('/[,\/\s]+/', trim($phone));
        $first = trim($parts[0] ?? '');
        if (empty($first)) return null;
        if (preg_match('/^[0-9]+$/', $first) && strlen($first) === 10 && !str_starts_with($first, '0')) {
            $first = '0' . $first;
        }
        return $first ?: null;
    }

    private function cleanEmail(?string $email): ?string
    {
        if (!$email) return null;
        $email = strtolower(trim($email));
        $parts = preg_split('/[,\s]+/', $email);
        $first = trim($parts[0] ?? '');
        return filter_var($first, FILTER_VALIDATE_EMAIL) ? $first : null;
    }

    private function cleanText(?string $text): ?string
    {
        if (!$text) return null;
        $text = preg_replace('/\s+/', ' ', trim($text));
        $text = str_replace(["\n", "\r"], ' ', $text);
        return $text ?: null;
    }

    private function detectSubgroups(?string $value): array
    {
        if (!$value) return [];
        $lower = strtolower(trim($value));
        $slugs = [];
        if (str_contains($lower, 'appsn')) $slugs[] = 'appsn';
        if (str_contains($lower, 'ysn')) $slugs[] = 'ysn';
        if (str_contains($lower, 'wis')) $slugs[] = 'wis';
        if (str_contains($lower, 'nasgl')) $slugs[] = 'nasgl';
        return $slugs;
    }

    private function parseSubgroupsFromNew(?string $value): array
    {
        if (!$value) return [];
        $lower = strtolower(trim($value));
        $slugs = [];
        if (str_contains($lower, 'appsn') || str_contains($lower, 'àppsn')) $slugs[] = 'appsn';
        if (str_contains($lower, 'ysn')) $slugs[] = 'ysn';
        if (str_contains($lower, 'wis')) $slugs[] = 'wis';
        if (str_contains($lower, 'nasgl')) $slugs[] = 'nasgl';
        return $slugs;
    }

    private function parseDob($value): ?string
    {
        if (!$value) return null;
        try {
            if (is_numeric($value)) {
                $date = Date::excelToDateTimeObject($value);
            } else {
                $date = new \DateTime($value);
            }
            $year = (int) $date->format('Y');
            if ($year < 1930 || $year > 2010) return null;
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generatePlaceholderEmail(string $firstName, string $lastName): string
    {
        $base = strtolower(Str::slug($firstName . '.' . $lastName, '.'));
        $email = "{$base}@nis-placeholder.local";
        $i = 1;
        while (User::where('email', $email)->exists()) {
            $email = "{$base}{$i}@nis-placeholder.local";
            $i++;
        }
        return $email;
    }

    private function logError(int $row, string $message): void
    {
        $this->errorLog[] = ['row' => $row, 'message' => $message];
    }
}
