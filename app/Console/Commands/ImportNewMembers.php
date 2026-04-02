<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\MemberProfile;
use App\Models\MembershipCategory;
use App\Models\Role;
use App\Models\Subgroup;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ImportNewMembers extends Command
{
    protected $signature = 'members:import-new
                            {file : Path to the Excel file (WEB.xlsx)}
                            {--dry-run : Preview without saving}
                            {--default-password=NISMember@2026 : Default password}';

    protected $description = 'Import members from NIS-NEW sheet. Updates existing members (by SURCON/email) or creates new ones.';

    private array $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Loading NIS-NEW sheet...");
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheetByName('NIS-NEW');

        if (!$worksheet) {
            $this->error("Sheet 'NIS-NEW' not found.");
            return self::FAILURE;
        }

        $rows = $worksheet->toArray(null, true, true, true);
        array_shift($rows); // Remove header
        $this->stats['total'] = count($rows);

        $categories = MembershipCategory::pluck('id', 'slug')->toArray();
        $memberRole = Role::where('slug', 'member')->first();
        $subgroups = Subgroup::pluck('id', 'slug')->toArray();

        $this->info("Processing {$this->stats['total']} rows...");
        $bar = $this->output->createProgressBar($this->stats['total']);

        /**
         * NIS-NEW columns:
         * A = (empty), B = NAMES, C = GENDER, D = NIS MEMBERSHIP NUMBER,
         * E = SURCON REG. NO., F = DAY & MONTH OF BIRTH,
         * G = SUB GROUP, H = OFFICE ADDRESS, I = RESIDENTIAL ADDRESS,
         * J = PHONE NO, K = E-MAIL, L = SURFIX
         */
        foreach ($rows as $row) {
            $bar->advance();

            $name = trim($row['B'] ?? '');
            if (empty($name)) {
                $this->stats['skipped']++;
                continue;
            }

            try {
                $surcon = preg_replace('/\s+/', '', trim($row['E'] ?? ''));
                $email = $this->cleanEmail($row['K'] ?? '');
                $phone = $this->cleanPhone($row['J'] ?? '');
                $nisId = $this->cleanNisId($row['D'] ?? '');

                // Try to find existing member by SURCON or email
                $existing = null;
                if ($surcon) {
                    $existing = User::where('surcon_reg_no', $surcon)->first();
                }
                if (!$existing && $email) {
                    $existing = User::where('email', $email)->first();
                }

                // Parse DOB
                $dob = $this->parseDob($row['F'] ?? null);

                // Parse subgroups
                $subgroupSlugs = $this->parseSubgroups($row['G'] ?? '');
                $subgroupIds = collect($subgroupSlugs)
                    ->map(fn($slug) => $subgroups[$slug] ?? null)
                    ->filter()
                    ->toArray();

                // Parse name
                $nameParts = $this->parseName($name);

                if ($existing && !$dryRun) {
                    // Update existing member with new data
                    DB::transaction(function () use ($existing, $row, $dob, $subgroupIds, $nameParts) {
                        // Update profile with DOB and addresses if missing
                        $profile = $existing->profile;
                        if ($profile) {
                            $updates = [];
                            if (!$profile->date_of_birth && $dob) $updates['date_of_birth'] = $dob;
                            if (!$profile->office_address && $row['H']) $updates['office_address'] = trim($row['H']);
                            if (!$profile->residential_address && $row['I']) $updates['residential_address'] = trim($row['I']);
                            if (!empty($updates)) $profile->update($updates);
                        }

                        // Sync subgroups (merge, don't replace)
                        if (!empty($subgroupIds)) {
                            $existing->subgroups()->syncWithoutDetaching($subgroupIds);
                        }
                    });

                    $this->stats['updated']++;
                } elseif (!$existing && !$dryRun) {
                    // Create new member
                    DB::transaction(function () use (
                        $nameParts, $email, $phone, $surcon, $nisId,
                        $row, $dob, $categories, $memberRole, $subgroupIds
                    ) {
                        $finalEmail = $email ?: $this->generateEmail($nameParts['first_name'], $nameParts['last_name']);

                        $user = User::create([
                            'first_name'             => $nameParts['first_name'],
                            'last_name'              => $nameParts['last_name'],
                            'other_names'            => $nameParts['other_names'],
                            'email'                  => $finalEmail,
                            'phone'                  => $phone,
                            'gender'                 => $this->normalizeGender($row['C'] ?? ''),
                            'surcon_reg_no'          => $surcon,
                            'nis_membership_id'      => $nisId,
                            'suffix'                 => trim($row['L'] ?? '') ?: null,
                            'membership_category_id' => $categories['member'] ?? null,
                            'role_id'                => $memberRole->id,
                            'status'                 => UserStatus::ACTIVE,
                            'password'               => Hash::make($this->option('default-password')),
                            'is_migrated'            => true,
                            'profile_completed'      => false,
                            'email_verified_at'      => now(),
                            'approved_at'            => now(),
                        ]);

                        MemberProfile::create([
                            'user_id'             => $user->id,
                            'office_address'      => trim($row['H'] ?? '') ?: null,
                            'residential_address' => trim($row['I'] ?? '') ?: null,
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
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Results: Created={$this->stats['created']}, Updated={$this->stats['updated']}, Skipped={$this->stats['skipped']}, Errors={$this->stats['errors']}");

        if ($dryRun) {
            $this->warn("DRY RUN — no data saved.");
        }

        return self::SUCCESS;
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

    private function parseDob($value): ?string
    {
        if (!$value) return null;

        // PhpSpreadsheet may return a DateTime string or Excel serial number
        try {
            if (is_numeric($value)) {
                $date = Date::excelToDateTimeObject($value);
            } else {
                $date = new \DateTime($value);
            }

            // Sanity check — reject dates in the future or too old
            $year = (int) $date->format('Y');
            if ($year < 1930 || $year > 2010) return null;

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseSubgroups(?string $value): array
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

    private function cleanEmail(?string $email): ?string
    {
        if (!$email) return null;
        $email = strtolower(trim($email));
        $parts = preg_split('/[,\s]+/', $email);
        $first = trim($parts[0] ?? '');
        return filter_var($first, FILTER_VALIDATE_EMAIL) ? $first : null;
    }

    private function cleanPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $parts = preg_split('/[,\/\s]+/', trim($phone));
        $first = trim($parts[0] ?? '');
        return $first ?: null;
    }

    private function cleanNisId(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);
        if (preg_match('/(NIS\s*\/\s*(?:FM|F|ASS)\s*\/\s*[\d]+)/i', $value, $m)) {
            return preg_replace('/\s+/', '', $m[1]);
        }
        if (preg_match('/^(FM\s*\/\s*[\d]+)/i', $value, $m)) {
            return 'NIS/' . preg_replace('/\s+/', '', $m[1]);
        }
        return null;
    }

    private function normalizeGender(?string $g): ?string
    {
        if (!$g) return null;
        $g = strtolower(trim($g));
        return match (true) {
            in_array($g, ['m', 'male']) => 'male',
            in_array($g, ['f', 'female']) => 'female',
            default => null,
        };
    }

    private function generateEmail(string $first, string $last): string
    {
        $base = strtolower(Str::slug($first . '.' . $last, '.'));
        $email = "{$base}@nis-placeholder.local";
        $i = 1;
        while (User::where('email', $email)->exists()) {
            $email = "{$base}{$i}@nis-placeholder.local";
            $i++;
        }
        return $email;
    }
}
