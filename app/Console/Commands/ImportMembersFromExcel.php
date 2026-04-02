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

class ImportMembersFromExcel extends Command
{
    protected $signature = 'members:import
                            {file : Path to the Excel file (WEB.xlsx)}
                            {--sheet=NIS-EXISTING : Sheet name to import from}
                            {--dry-run : Preview import without saving to database}
                            {--default-password=NISMember@2026 : Default password for imported accounts}';

    protected $description = 'Import existing members from NIS Excel spreadsheet into the database';

    private array $stats = [
        'total'    => 0,
        'imported' => 0,
        'skipped'  => 0,
        'errors'   => 0,
    ];

    private array $errorLog = [];

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $sheetName = $this->option('sheet');
        $dryRun = $this->option('dry-run');
        $defaultPassword = $this->option('default-password');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info("┌─────────────────────────────────────────────────┐");
        $this->info("│  NIS Member Import Tool                         │");
        $this->info("│  File: {$filePath}");
        $this->info("│  Sheet: {$sheetName}");
        $this->info("│  Mode: " . ($dryRun ? "DRY RUN (no data will be saved)" : "LIVE IMPORT"));
        $this->info("└─────────────────────────────────────────────────┘");
        $this->newLine();

        // Load categories and roles
        $categories = MembershipCategory::pluck('id', 'slug')->toArray();
        $memberRole = Role::where('slug', 'member')->first();
        $subgroups = Subgroup::pluck('id', 'slug')->toArray();

        if (!$memberRole) {
            $this->error("Member role not found. Run db:seed first.");
            return self::FAILURE;
        }

        // Load spreadsheet
        $this->info("Loading spreadsheet...");
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getSheetByName($sheetName);

            if (!$worksheet) {
                $this->error("Sheet '{$sheetName}' not found. Available: " .
                    implode(', ', $spreadsheet->getSheetNames()));
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Failed to load spreadsheet: {$e->getMessage()}");
            return self::FAILURE;
        }

        $rows = $worksheet->toArray(null, true, true, true);
        $header = array_shift($rows); // Remove header row
        $this->stats['total'] = count($rows);

        $this->info("Found {$this->stats['total']} rows to process.");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($this->stats['total']);
        $progressBar->start();

        foreach ($rows as $rowIndex => $row) {
            $progressBar->advance();
            $actualRow = $rowIndex + 2; // Account for header + 1-indexed

            try {
                $parsed = $this->parseRow($row, $actualRow);

                if (!$parsed) {
                    $this->stats['skipped']++;
                    continue;
                }

                // Check for duplicate by email or SURCON
                if ($parsed['email'] && User::where('email', $parsed['email'])->exists()) {
                    $this->logError($actualRow, "Duplicate email: {$parsed['email']}");
                    $this->stats['skipped']++;
                    continue;
                }

                if ($parsed['surcon_reg_no'] && User::where('surcon_reg_no', $parsed['surcon_reg_no'])->exists()) {
                    $this->logError($actualRow, "Duplicate SURCON: {$parsed['surcon_reg_no']}");
                    $this->stats['skipped']++;
                    continue;
                }

                // Determine membership category from the messy NIS ID column
                $categoryId = $this->detectCategory($parsed['raw_nis_id'], $categories);

                // Extract clean NIS membership ID
                $nisId = $this->extractNisId($parsed['raw_nis_id']);

                if (!$dryRun) {
                    DB::transaction(function () use (
                        $parsed, $categoryId, $nisId, $memberRole, $defaultPassword, $subgroups
                    ) {
                        // Generate email if missing
                        $email = $parsed['email'];
                        if (!$email) {
                            $email = $this->generatePlaceholderEmail($parsed['first_name'], $parsed['last_name']);
                        }

                        $user = User::create([
                            'first_name'             => $parsed['first_name'],
                            'last_name'              => $parsed['last_name'],
                            'other_names'            => $parsed['other_names'],
                            'email'                  => $email,
                            'phone'                  => $parsed['phone'],
                            'gender'                 => $parsed['gender'],
                            'surcon_reg_no'          => $parsed['surcon_reg_no'],
                            'nis_membership_id'      => $nisId,
                            'suffix'                 => $parsed['suffix'],
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
                            'office_address'      => $parsed['office_address'],
                            'residential_address' => $parsed['residential_address'],
                        ]);

                        // Attach subgroups if detected
                        if (!empty($parsed['subgroup_slugs'])) {
                            $subgroupIds = collect($parsed['subgroup_slugs'])
                                ->map(fn($slug) => $subgroups[$slug] ?? null)
                                ->filter()
                                ->toArray();
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

        $progressBar->finish();
        $this->newLine(2);

        // Print results
        $this->info("┌─────────────────────────────────────────────────┐");
        $this->info("│  Import Results                                  │");
        $this->info("├─────────────────────────────────────────────────┤");
        $this->info("│  Total rows:    {$this->stats['total']}");
        $this->info("│  Imported:      {$this->stats['imported']}");
        $this->info("│  Skipped:       {$this->stats['skipped']}");
        $this->info("│  Errors:        {$this->stats['errors']}");
        $this->info("└─────────────────────────────────────────────────┘");

        if (!empty($this->errorLog)) {
            $this->newLine();
            $this->warn("Errors encountered:");
            foreach (array_slice($this->errorLog, 0, 20) as $error) {
                $this->line("  Row {$error['row']}: {$error['message']}");
            }
            if (count($this->errorLog) > 20) {
                $this->line("  ... and " . (count($this->errorLog) - 20) . " more errors.");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn("This was a DRY RUN. No data was saved.");
            $this->warn("Run without --dry-run to perform actual import.");
        }

        return self::SUCCESS;
    }

    /**
     * Parse a row from the NIS-EXISTING sheet.
     *
     * Columns: A=S/N, B=NAMES, C=GENDER, D=SURCON REG. NO.,
     *          E=Member NIS ID, F=(blank), G=OFFICE ADDRESS,
     *          H=RESIDENTIAL ADDRESS, I=PHONE NO, J=E-MAIL,
     *          K=SURFIX, L=SUBGROUP
     */
    private function parseRow(array $row, int $rowNum): ?array
    {
        $name = trim($row['B'] ?? '');

        if (empty($name)) {
            return null;
        }

        // Parse name — format is typically "LASTNAME FIRSTNAME OTHERNAME"
        // Some are "LASTNAME, FIRSTNAME OTHERNAME"
        $nameParts = $this->parseName($name);

        // Parse gender
        $gender = $this->normalizeGender($row['C'] ?? '');

        // Parse SURCON reg number — clean it up
        $surcon = $this->cleanSurcon($row['D'] ?? '');

        // Raw NIS ID column — contains mix of IDs and membership types
        $rawNisId = trim($row['E'] ?? '');

        // Addresses
        $officeAddress = $this->cleanText($row['G'] ?? '');
        $residentialAddress = $this->cleanText($row['H'] ?? '');

        // Phone — take first number if multiple
        $phone = $this->cleanPhone($row['I'] ?? '');

        // Email
        $email = $this->cleanEmail($row['J'] ?? '');

        // Suffix
        $suffix = $this->cleanText($row['K'] ?? '');

        // Subgroup — detect from column L or the NIS-NEW subgroup column
        $subgroupSlugs = $this->detectSubgroups($row['L'] ?? '');

        return [
            'first_name'          => $nameParts['first_name'],
            'last_name'           => $nameParts['last_name'],
            'other_names'         => $nameParts['other_names'],
            'gender'              => $gender,
            'surcon_reg_no'       => $surcon,
            'raw_nis_id'          => $rawNisId,
            'office_address'      => $officeAddress,
            'residential_address' => $residentialAddress,
            'phone'               => $phone,
            'email'               => $email,
            'suffix'              => $suffix,
            'subgroup_slugs'      => $subgroupSlugs,
        ];
    }

    /**
     * Parse name string into first, last, and other names.
     */
    private function parseName(string $name): array
    {
        // Remove excess whitespace
        $name = preg_replace('/\s+/', ' ', trim($name));

        // Handle "LASTNAME, FIRSTNAME OTHERNAME" format
        if (str_contains($name, ',')) {
            $parts = explode(',', $name, 2);
            $lastName = trim($parts[0]);
            $rest = trim($parts[1] ?? '');
            $restParts = explode(' ', $rest);

            return [
                'last_name'   => Str::title($lastName),
                'first_name'  => Str::title($restParts[0] ?? ''),
                'other_names' => Str::title(implode(' ', array_slice($restParts, 1))) ?: null,
            ];
        }

        // Handle "LASTNAME FIRSTNAME OTHERNAME" format
        $parts = explode(' ', $name);

        return [
            'last_name'   => Str::title($parts[0] ?? ''),
            'first_name'  => Str::title($parts[1] ?? ''),
            'other_names' => count($parts) > 2 ? Str::title(implode(' ', array_slice($parts, 2))) : null,
        ];
    }

    /**
     * Normalize inconsistent gender values.
     */
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

    /**
     * Clean SURCON registration number.
     */
    private function cleanSurcon(?string $surcon): ?string
    {
        if (!$surcon) return null;

        $surcon = trim($surcon);

        // Remove "Pupil Surveyor", "Associate Member" type strings
        if (preg_match('/^[a-zA-Z\s]+$/', $surcon)) {
            return null; // Not a valid SURCON number
        }

        // Clean spaces around numbers
        return preg_replace('/\s+/', '', $surcon);
    }

    /**
     * Detect membership category from the messy NIS ID column.
     */
    private function detectCategory(?string $rawNisId, array $categories): ?int
    {
        if (!$rawNisId) return $categories['member'] ?? null;

        $lower = strtolower($rawNisId);

        // Direct type matches
        if (str_contains($lower, 'fellow') || str_contains($lower, 'felow')) {
            return $categories['fellow'] ?? null;
        }
        if (str_contains($lower, 'associate') || str_contains($lower, 'asocciate') || $lower === 'anis') {
            return $categories['associate'] ?? null;
        }
        if (str_contains($lower, 'student')) {
            return $categories['student'] ?? null;
        }
        if (str_contains($lower, 'pupil') || str_contains($lower, 'probationer')) {
            return $categories['probationer'] ?? null;
        }
        if (str_contains($lower, 'technician') || str_contains($lower, 'technologist') || str_contains($lower, 'survey tech')) {
            return $categories['probationer'] ?? null;
        }

        // NIS ID pattern detection
        if (preg_match('/nis\s*\/\s*f\s*\//', $lower)) {
            return $categories['fellow'] ?? null; // NIS/F/ = Fellow
        }
        if (preg_match('/nis\s*\/\s*ass\s*\//', $lower)) {
            return $categories['associate'] ?? null; // NIS/ASS/ = Associate
        }
        if (preg_match('/nis\s*\/\s*fm\s*\//', $lower)) {
            return $categories['member'] ?? null; // NIS/FM/ = Full Member
        }

        // Keywords
        if (str_contains($lower, 'mnis') || str_contains($lower, 'member') || str_contains($lower, 'fullmember') || str_contains($lower, 'full member') || str_contains($lower, 'full mumber') || str_contains($lower, 'memeber')) {
            return $categories['member'] ?? null;
        }

        // Default to member
        return $categories['member'] ?? null;
    }

    /**
     * Extract a clean NIS membership ID from the messy column.
     */
    private function extractNisId(?string $rawNisId): ?string
    {
        if (!$rawNisId) return null;

        // Try to find NIS/FM/XXXX or NIS/F/XXX or NIS/ASS/XXX pattern
        if (preg_match('/(NIS\s*\/\s*(?:FM|F|ASS)\s*\/\s*[\d]+)/i', $rawNisId, $matches)) {
            // Clean spaces
            return preg_replace('/\s+/', '', $matches[1]);
        }

        // Try to find standalone "FM/XXXX" pattern
        if (preg_match('/(FM\s*\/\s*[\d]+)/i', $rawNisId, $matches)) {
            return 'NIS/' . preg_replace('/\s+/', '', $matches[1]);
        }

        // If it starts with NIS, clean it
        if (stripos($rawNisId, 'NIS') === 0) {
            return preg_replace('/\s+/', '', $rawNisId);
        }

        // If it's just a text label (Fellow, Member, etc.), no NIS ID
        if (preg_match('/^[a-zA-Z\s]+$/', trim($rawNisId))) {
            return null;
        }

        return null;
    }

    /**
     * Clean phone number.
     */
    private function cleanPhone(?string $phone): ?string
    {
        if (!$phone) return null;

        $phone = trim($phone);

        // Take first number if multiple separated by comma, slash, or space
        $parts = preg_split('/[,\/\s]+/', $phone);
        $first = trim($parts[0] ?? '');

        if (empty($first)) return null;

        // Ensure it starts with 0 or +234
        if (preg_match('/^[0-9]+$/', $first)) {
            if (strlen($first) === 10 && !str_starts_with($first, '0')) {
                $first = '0' . $first;
            }
        }

        return $first ?: null;
    }

    /**
     * Clean email address.
     */
    private function cleanEmail(?string $email): ?string
    {
        if (!$email) return null;

        $email = strtolower(trim($email));

        // Take first email if multiple
        $parts = preg_split('/[,\s]+/', $email);
        $first = trim($parts[0] ?? '');

        // Basic validation
        if (!filter_var($first, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $first;
    }

    /**
     * Clean generic text field.
     */
    private function cleanText(?string $text): ?string
    {
        if (!$text) return null;

        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text); // Collapse whitespace
        $text = str_replace(["\n", "\r"], ' ', $text); // Remove newlines

        return $text ?: null;
    }

    /**
     * Detect subgroups from the subgroup column value.
     */
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

    /**
     * Generate a placeholder email for members without one.
     */
    private function generatePlaceholderEmail(string $firstName, string $lastName): string
    {
        $base = strtolower(Str::slug($firstName . '.' . $lastName, '.'));
        $email = "{$base}@nis-placeholder.local";

        // Ensure uniqueness
        $counter = 1;
        while (User::where('email', $email)->exists()) {
            $email = "{$base}{$counter}@nis-placeholder.local";
            $counter++;
        }

        return $email;
    }

    /**
     * Log an error for reporting.
     */
    private function logError(int $row, string $message): void
    {
        $this->errorLog[] = [
            'row'     => $row,
            'message' => $message,
        ];
    }
}
