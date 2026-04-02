<?php

namespace Database\Seeders;

use App\Models\Subgroup;
use Illuminate\Database\Seeder;

class SubgroupSeeder extends Seeder
{
    public function run(): void
    {
        $subgroups = [
            [
                'name'        => 'APPSN',
                'slug'        => 'appsn',
                'full_name'   => 'Association of Private Practising Surveyors of Nigeria',
                'description' => 'Subgroup for private practising surveyors.',
                'chairperson' => null,
            ],
            [
                'name'        => 'YSN',
                'slug'        => 'ysn',
                'full_name'   => 'Young Surveyors Network',
                'description' => 'Subgroup for young surveyors fostering networking and professional growth.',
                'chairperson' => 'Surv. Osinaike Sasaeniyan Segun',
            ],
            [
                'name'        => 'WIS',
                'slug'        => 'wis',
                'full_name'   => 'Women in Surveying',
                'description' => 'Subgroup empowering women in the surveying profession.',
                'chairperson' => 'Surv. Dairo Basirat Oyelola, MNIS',
            ],
            [
                'name'        => 'NASGL',
                'slug'        => 'nasgl',
                'full_name'   => 'Nigerian Association of Surveying and Geoinformatics Lecturers',
                'description' => 'Subgroup for lecturers and academics in surveying and geoinformatics.',
                'chairperson' => 'Surv. Qaadri Jeelel A.',
            ],
            [
                'name'        => 'NISQS',
                'slug'        => 'nisqs',
                'full_name'   => 'Nigerian Institution of Surveying & Geoinformation Students',
                'description' => 'Student body of the Nigerian Institution of Surveyors.',
                'chairperson' => null,
            ],
        ];

        foreach ($subgroups as $subgroup) {
            Subgroup::create($subgroup);
        }
    }
}
