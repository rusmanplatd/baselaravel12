<?php

namespace Database\Seeders;

use App\Models\OrganizationPositionLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationPositionLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultUserId = Str::ulid();

        $levels = [
            [
                'code' => 'board_member',
                'name' => 'Board Member',
                'description' => 'Board of directors/commissioners member',
                'hierarchy_level' => 1,
                'sort_order' => 1,
            ],
            [
                'code' => 'c_level',
                'name' => 'C-Level',
                'description' => 'Chief executive positions (CEO, CTO, CFO, etc.)',
                'hierarchy_level' => 2,
                'sort_order' => 2,
            ],
            [
                'code' => 'vice_president',
                'name' => 'Vice President',
                'description' => 'Vice president level positions',
                'hierarchy_level' => 3,
                'sort_order' => 3,
            ],
            [
                'code' => 'director',
                'name' => 'Director',
                'description' => 'Director level positions',
                'hierarchy_level' => 4,
                'sort_order' => 4,
            ],
            [
                'code' => 'senior_manager',
                'name' => 'Senior Manager',
                'description' => 'Senior management positions',
                'hierarchy_level' => 5,
                'sort_order' => 5,
            ],
            [
                'code' => 'manager',
                'name' => 'Manager',
                'description' => 'Management positions',
                'hierarchy_level' => 6,
                'sort_order' => 6,
            ],
            [
                'code' => 'assistant_manager',
                'name' => 'Assistant Manager',
                'description' => 'Assistant management positions',
                'hierarchy_level' => 7,
                'sort_order' => 7,
            ],
            [
                'code' => 'supervisor',
                'name' => 'Supervisor',
                'description' => 'Supervisory positions',
                'hierarchy_level' => 8,
                'sort_order' => 8,
            ],
            [
                'code' => 'senior_staff',
                'name' => 'Senior Staff',
                'description' => 'Senior staff positions',
                'hierarchy_level' => 9,
                'sort_order' => 9,
            ],
            [
                'code' => 'staff',
                'name' => 'Staff',
                'description' => 'Staff positions',
                'hierarchy_level' => 10,
                'sort_order' => 10,
            ],
            [
                'code' => 'junior_staff',
                'name' => 'Junior Staff',
                'description' => 'Junior staff positions',
                'hierarchy_level' => 11,
                'sort_order' => 11,
            ],
        ];

        foreach ($levels as $level) {
            OrganizationPositionLevel::create([
                ...$level,
                'is_active' => true,
                'created_by' => $defaultUserId,
                'updated_by' => $defaultUserId,
            ]);
        }
    }
}
