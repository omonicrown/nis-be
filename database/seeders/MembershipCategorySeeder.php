<?php

namespace Database\Seeders;

use App\Models\MembershipCategory;
use Illuminate\Database\Seeder;

class MembershipCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'         => 'Student',
                'slug'         => 'student',
                'designation'  => null,
                'description'  => 'Student membership for persons pursuing full-time course of study in Surveying and Geo-informatics.',
                'requirements' => 'Must be pursuing a full-time course of study in Surveying and Geo-informatics at any Institution with a programme accredited by SURCON. The University/Polytechnic must list the student in their Student membership letter to the State Branch.',
                'annual_fee'   => 2500.00,
                'rank'         => 1,
            ],
            [
                'name'         => 'Probationer',
                'slug'         => 'probationer',
                'designation'  => null,
                'description'  => 'Probationer membership for technicians and ND holders.',
                'requirements' => 'Any person who passed the SURCON examination for Technicians with at least two years post-graduate experience, or any person who has a National Diploma in Surveying and Geo-informatics approved by the Institution and recognized by SURCON.',
                'annual_fee'   => 2500.00,
                'rank'         => 2,
            ],
            [
                'name'         => 'Associate',
                'slug'         => 'associate',
                'designation'  => 'ANIS',
                'description'  => 'Associate of the Nigerian Institution of Surveyors.',
                'requirements' => 'At least a First Degree, Professional Diploma, Post Graduate Diploma in Surveying and Geo-informatics or equivalent recognized by SURCON, or HND in Surveying and Geo-informatics, or any person who has passed the SURCON examination for Technologist.',
                'annual_fee'   => 5000.00,
                'rank'         => 3,
            ],
            [
                'name'         => 'Member',
                'slug'         => 'member',
                'designation'  => 'MNIS',
                'description'  => 'Member of the Nigerian Institution of Surveyors.',
                'requirements' => 'Must have SURCON Registration with active professional practice.',
                'annual_fee'   => 7500.00,
                'rank'         => 4,
            ],
            [
                'name'         => 'Fellow',
                'slug'         => 'fellow',
                'designation'  => 'FNIS',
                'description'  => 'Fellow of the Nigerian Institution of Surveyors.',
                'requirements' => 'Must have SURCON Registration with active professional practice and meet the criteria set by the Board of Fellows.',
                'annual_fee'   => 20000.00,
                'rank'         => 5,
            ],
        ];

        foreach ($categories as $category) {
            MembershipCategory::create($category);
        }
    }
}
