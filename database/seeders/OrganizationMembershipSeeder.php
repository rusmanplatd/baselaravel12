<?php

namespace Database\Seeders;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationMembershipSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user ID and IDs for created_by/updated_by and references
        $adminUserId = config('seeder.admin_user_id', 1);
        $orgIds = config('seeder.organization_ids');
        $unitIds = config('seeder.unit_ids');
        $positionIds = config('seeder.position_ids');

        // First, create additional users for memberships
        $users = [
            [
                'name' => 'John Smith',
                'email' => 'john.smith@techcorp.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Jane Doe',
                'email' => 'jane.doe@techcorp.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Mike Johnson',
                'email' => 'mike.johnson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Sarah Wilson',
                'email' => 'sarah.wilson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'David Brown',
                'email' => 'david.brown@techcorpdata.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Emily Davis',
                'email' => 'emily.davis@techcorpdata.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Robert Taylor',
                'email' => 'robert.taylor@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Lisa Anderson',
                'email' => 'lisa.anderson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'michael.chen@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Jennifer Martinez',
                'email' => 'jennifer.martinez@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Alex Thompson',
                'email' => 'alex.thompson@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
            [
                'name' => 'Maria Rodriguez',
                'email' => 'maria.rodriguez@techcorpsoftware.com',
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
            ],
        ];

        foreach ($users as $userData) {
            $userData['created_by'] = $adminUserId;
            $userData['updated_by'] = $adminUserId;
            User::firstOrCreate(['email' => $userData['email']], $userData);
        }

        // Get user IDs
        $testUser = User::where('email', 'test@example.com')->first();
        $johnSmith = User::where('email', 'john.smith@techcorp.com')->first();
        $janeDoe = User::where('email', 'jane.doe@techcorp.com')->first();
        $mikeJohnson = User::where('email', 'mike.johnson@techcorpsoftware.com')->first();
        $sarahWilson = User::where('email', 'sarah.wilson@techcorpsoftware.com')->first();
        $davidBrown = User::where('email', 'david.brown@techcorpdata.com')->first();
        $emilyDavis = User::where('email', 'emily.davis@techcorpdata.com')->first();
        $robertTaylor = User::where('email', 'robert.taylor@techcorpsoftware.com')->first();
        $lisaAnderson = User::where('email', 'lisa.anderson@techcorpsoftware.com')->first();
        $michaelChen = User::where('email', 'michael.chen@techcorpsoftware.com')->first();
        $jenniferMartinez = User::where('email', 'jennifer.martinez@techcorpsoftware.com')->first();
        $alexThompson = User::where('email', 'alex.thompson@techcorpsoftware.com')->first();
        $mariaRodriguez = User::where('email', 'maria.rodriguez@techcorpsoftware.com')->first();

        $memberships = [
            // TechCorp Holdings Board Members
            [
                'user_id' => $johnSmith->id,
                'organization_id' => $orgIds['techcorp_holdings'],
                'organization_unit_id' => $unitIds['board_of_directors'],
                'organization_position_id' => $positionIds['ceo'],
                'membership_type' => 'board_member',
                'start_date' => '2020-01-15',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['strategic_planning', 'investor_relations'],
            ],
            [
                'user_id' => $janeDoe->id,
                'organization_id' => $orgIds['techcorp_holdings'],
                'organization_unit_id' => $unitIds['board_of_directors'],
                'organization_position_id' => $positionIds['cfo'],
                'membership_type' => 'board_member',
                'start_date' => '2020-02-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['financial_oversight', 'risk_management'],
            ],

            // TechCorp Software Leadership
            [
                'user_id' => $mikeJohnson->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['executive_office'],
                'organization_position_id' => $positionIds['managing_director'],
                'membership_type' => 'employee',
                'start_date' => '2021-03-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['business_development', 'client_relations'],
            ],
            [
                'user_id' => $sarahWilson->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['executive_office'],
                'organization_position_id' => $positionIds['cto'],
                'membership_type' => 'employee',
                'start_date' => '2021-03-15',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['innovation', 'technical_strategy'],
            ],
            [
                'user_id' => $robertTaylor->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['engineering_division'],
                'organization_position_id' => $positionIds['vp_engineering'],
                'membership_type' => 'employee',
                'start_date' => '2021-04-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['team_leadership', 'process_improvement'],
            ],

            // TechCorp Software Development Teams
            [
                'user_id' => $lisaAnderson->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['frontend_team'],
                'organization_position_id' => $positionIds['senior_frontend_dev'],
                'membership_type' => 'employee',
                'start_date' => '2021-05-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['ui_architecture', 'mentoring'],
            ],
            [
                'user_id' => $michaelChen->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['frontend_team'],
                'organization_position_id' => $positionIds['frontend_dev'],
                'membership_type' => 'employee',
                'start_date' => '2022-01-15',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['component_development', 'testing'],
            ],
            [
                'user_id' => $jenniferMartinez->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['backend_team'],
                'organization_position_id' => $positionIds['senior_backend_dev'],
                'membership_type' => 'employee',
                'start_date' => '2021-06-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['system_architecture', 'database_design'],
            ],
            [
                'user_id' => $alexThompson->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['backend_team'],
                'organization_position_id' => $positionIds['backend_dev'],
                'membership_type' => 'employee',
                'start_date' => '2022-02-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['api_development', 'integration'],
            ],
            [
                'user_id' => $mariaRodriguez->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['qa_department'],
                'organization_position_id' => $positionIds['qa_manager'],
                'membership_type' => 'employee',
                'start_date' => '2021-07-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['process_definition', 'quality_metrics'],
            ],

            // TechCorp Data Leadership
            [
                'user_id' => $davidBrown->id,
                'organization_id' => $orgIds['techcorp_data'],
                'organization_unit_id' => null, // Direct to organization
                'organization_position_id' => null, // Custom role
                'membership_type' => 'employee',
                'start_date' => '2021-06-15',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['managing_director', 'ai_strategy'],
            ],
            [
                'user_id' => $emilyDavis->id,
                'organization_id' => $orgIds['techcorp_data'],
                'organization_unit_id' => $unitIds['ai_research_division'],
                'organization_position_id' => null, // Custom role
                'membership_type' => 'employee',
                'start_date' => '2021-07-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['head_of_ai', 'research_leadership'],
            ],

            // Additional memberships for contractors/consultants
            [
                'user_id' => $testUser->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => $unitIds['engineering_division'],
                'organization_position_id' => null,
                'membership_type' => 'consultant',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'status' => 'active',
                'additional_roles' => ['technical_advisory', 'code_review'],
            ],
        ];

        foreach ($memberships as $membershipData) {
            $membershipData['created_by'] = $adminUserId;
            $membershipData['updated_by'] = $adminUserId;
            OrganizationMembership::create($membershipData);
        }
    }
}