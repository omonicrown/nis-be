<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ExportMembers extends Command
{
    protected $signature = 'members:export
                            {--output=members_export.csv : Output file path}
                            {--status=active : Filter by status (active, pending, all)}
                            {--category= : Filter by membership category slug}';

    protected $description = 'Export members to CSV file';

    public function handle(): int
    {
        $status = $this->option('status');
        $category = $this->option('category');
        $output = $this->option('output');

        $query = User::with(['membershipCategory', 'profile', 'subgroups']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($category) {
            $query->whereHas('membershipCategory', fn($q) => $q->where('slug', $category));
        }

        $members = $query->orderBy('last_name')->get();

        $this->info("Exporting {$members->count()} members...");

        $handle = fopen($output, 'w');

        // Header row
        fputcsv($handle, [
            'S/N', 'Last Name', 'First Name', 'Other Names', 'Gender',
            'Email', 'Phone', 'SURCON Reg No', 'NIS Membership ID',
            'Membership Category', 'Designation', 'Status',
            'Office Address', 'Residential Address', 'Date of Birth',
            'Subgroups', 'Registered At',
        ]);

        foreach ($members as $i => $member) {
            $profile = $member->profile;

            fputcsv($handle, [
                $i + 1,
                $member->last_name,
                $member->first_name,
                $member->other_names,
                $member->gender,
                $member->email,
                $member->phone,
                $member->surcon_reg_no,
                $member->nis_membership_id,
                $member->membershipCategory?->name,
                $member->membershipCategory?->designation,
                $member->status?->value,
                $profile?->office_address,
                $profile?->residential_address,
                $profile?->date_of_birth?->format('Y-m-d'),
                $member->subgroups->pluck('name')->implode(', '),
                $member->created_at?->format('Y-m-d'),
            ]);
        }

        fclose($handle);

        $this->info("Exported to: {$output}");

        return self::SUCCESS;
    }
}
