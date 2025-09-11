<?php

namespace Database\Seeders;

use App\Models\Auth\Role;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class OrganizationMembershipSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user ID and IDs for created_by/updated_by and references
        $adminUserId = config('seeder.admin_user_id', 1);
        $orgIds = config('seeder.organization_ids');
        $unitIds = config('seeder.unit_ids');
        $positionIds = config('seeder.position_ids');


        // Get user IDs
        $testUser = User::where('email', 'test@example.com')->first();
        $johnSmith = User::where('email', 'john.smith@techcorp.com')->first();
        $janeDoe = User::where('email', 'jane.doe@techcorp.com')->first();

        // Check if required users exist
        if (! $testUser || ! $johnSmith || ! $janeDoe) {
            throw new \Exception('Required users not found. Please run UserSeeder first.');
        }
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

        // Add more diverse memberships for new organizations and roles
        $additionalMemberships = [
            // Cloud team members
            [
                'user_id' => $testUser->id,
                'organization_id' => $orgIds['techcorp_cloud'],
                'organization_unit_id' => null,
                'organization_position_id' => null,
                'membership_type' => 'contractor',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'status' => 'active',
                'additional_roles' => ['cloud_architect', 'devops_consultant'],
            ],

            // Security team members
            [
                'user_id' => User::where('email', 'admin@example.com')->first()->id,
                'organization_id' => $orgIds['techcorp_security'],
                'organization_unit_id' => null,
                'organization_position_id' => null,
                'membership_type' => 'employee',
                'start_date' => '2023-01-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['security_lead', 'incident_commander'],
            ],

            // International branch memberships
            [
                'user_id' => $robertTaylor->id,
                'organization_id' => $orgIds['software_europe'],
                'organization_unit_id' => null,
                'organization_position_id' => null,
                'membership_type' => 'employee',
                'start_date' => '2023-01-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['branch_coordinator', 'eu_compliance'],
            ],
            [
                'user_id' => $lisaAnderson->id,
                'organization_id' => $orgIds['software_asia'],
                'organization_unit_id' => null,
                'organization_position_id' => null,
                'membership_type' => 'employee',
                'start_date' => '2023-06-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['asia_coordinator', 'cultural_liaison'],
            ],

            // Cross-organization memberships
            [
                'user_id' => $mikeJohnson->id,
                'organization_id' => $orgIds['techcorp_holdings'],
                'organization_unit_id' => null,
                'organization_position_id' => null,
                'membership_type' => 'board_member',
                'start_date' => '2022-01-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['strategic_advisor', 'technology_committee'],
            ],

            // Consultants and advisors
            [
                'user_id' => $davidBrown->id,
                'organization_id' => $orgIds['techcorp_holdings'],
                'organization_unit_id' => null,
                'organization_position_id' => null,
                'membership_type' => 'consultant',
                'start_date' => '2023-01-01',
                'end_date' => '2025-12-31',
                'status' => 'active',
                'additional_roles' => ['ai_advisor', 'data_strategy'],
            ],

            // Part-time and temporary roles
            [
                'user_id' => $emilyDavis->id,
                'organization_id' => $orgIds['techcorp_software'],
                'organization_unit_id' => null,
                'organization_position_id' => null,
                'membership_type' => 'consultant',
                'start_date' => '2024-01-01',
                'end_date' => null,
                'status' => 'active',
                'additional_roles' => ['ai_consultant', 'research_liaison'],
            ],

            // Seasonal/project-based roles
            [
                'user_id' => $sarahWilson->id,
                'organization_id' => $orgIds['techcorp_cloud'],
                'organization_unit_id' => null,
                'organization_position_id' => null,
                'membership_type' => 'contractor',
                'start_date' => '2024-03-01',
                'end_date' => '2024-09-30',
                'status' => 'active',
                'additional_roles' => ['cloud_migration_lead', 'technical_architect'],
            ],
        ];

        $allMemberships = array_merge($memberships, $additionalMemberships);

        foreach ($allMemberships as $membershipData) {
            $membershipData['created_by'] = $adminUserId;
            $membershipData['updated_by'] = $adminUserId;
            OrganizationMembership::create($membershipData);
        }

        // Assign roles and permissions based on organizational positions and membership types
        $this->assignRolesAndPermissions($adminUserId);
    }

    /**
     * Assign roles and permissions to users based on their organizational positions
     */
    private function assignRolesAndPermissions($adminUserId): void
    {
        // Get organization IDs from config
        $orgIds = config('seeder.organization_ids');

        // Role assignments based on organizational hierarchy and responsibilities
        $roleAssignments = [
            // C-Level Executives - Super Admin or Organization Admin roles
            'john.smith@techcorp.com' => [
                'roles' => ['super-admin', 'organization-admin'],
                'organization_ids' => [$orgIds['techcorp_holdings']],
                'reason' => 'CEO of TechCorp Holdings - needs full system access',
            ],
            'jane.doe@techcorp.com' => [
                'roles' => ['organization-admin'],
                'organization_ids' => [$orgIds['techcorp_holdings']],
                'reason' => 'CFO - needs organization administration access',
            ],

            // Managing Directors and CTOs - Admin/Manager role
            'mike.johnson@techcorpsoftware.com' => [
                'roles' => ['organization-admin'],
                'organization_ids' => [$orgIds['techcorp_software']],
                'reason' => 'Managing Director of TechCorp Software',
            ],
            'sarah.wilson@techcorpsoftware.com' => [
                'roles' => ['organization-admin'],
                'organization_ids' => [$orgIds['techcorp_software']],
                'reason' => 'CTO - needs technical and organizational oversight',
            ],
            'david.brown@techcorpdata.com' => [
                'roles' => ['organization-admin'],
                'organization_ids' => [$orgIds['techcorp_data']],
                'reason' => 'Managing Director of TechCorp Data',
            ],

            // VPs and Senior Leadership - Manager role
            'robert.taylor@techcorpsoftware.com' => [
                'roles' => ['manager'],
                'organization_ids' => [$orgIds['techcorp_software']],
                'reason' => 'VP of Engineering - manages engineering teams',
            ],
            'emily.davis@techcorpdata.com' => [
                'roles' => ['manager'],
                'organization_ids' => [$orgIds['techcorp_data']],
                'reason' => 'Head of AI Research - manages research teams',
            ],
            'maria.rodriguez@techcorpsoftware.com' => [
                'roles' => ['manager'],
                'organization_ids' => [$orgIds['techcorp_software']],
                'reason' => 'QA Manager - manages quality assurance processes',
            ],

            // Senior Developers and Team Leads - Manager role (for their teams)
            'lisa.anderson@techcorpsoftware.com' => [
                'roles' => ['manager'],
                'organization_ids' => [$orgIds['techcorp_software']],
                'reason' => 'Senior Frontend Developer - technical team lead',
            ],
            'jennifer.martinez@techcorpsoftware.com' => [
                'roles' => ['manager'],
                'organization_ids' => [$orgIds['techcorp_software']],
                'reason' => 'Senior Backend Developer - technical team lead',
            ],

            // Regular employees - Employee role
            'michael.chen@techcorpsoftware.com' => [
                'roles' => ['employee'],
                'organization_ids' => [$orgIds['techcorp_software']],
                'reason' => 'Frontend Developer',
            ],
            'alex.thompson@techcorpsoftware.com' => [
                'roles' => ['employee'],
                'organization_ids' => [$orgIds['techcorp_software']],
                'reason' => 'Backend Developer',
            ],

            // System users with special roles
            'test@example.com' => [
                'roles' => ['consultant'],
                'organization_ids' => [$orgIds['techcorp_software'], $orgIds['techcorp_cloud']],
                'reason' => 'Technical consultant across multiple organizations',
            ],
            'admin@example.com' => [
                'roles' => ['super-admin'],
                'organization_ids' => [$orgIds['techcorp_security']],
                'reason' => 'Security administrator with cross-organization access',
            ],
        ];

        // Get default organization for role assignments
        $defaultOrgId = null;
        if (config('permission.teams', false)) {
            $defaultOrg = \App\Models\Organization::where('organization_code', 'DEFAULT')->first();
            $defaultOrgId = $defaultOrg?->id;
        }

        foreach ($roleAssignments as $email => $assignment) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            foreach ($assignment['roles'] as $roleName) {
                try {
                    // Handle team-based roles
                    if ($defaultOrgId) {
                        $role = Role::where('name', $roleName)->where('team_id', $defaultOrgId)->first();
                        if ($role) {
                            // Check if role assignment already exists to avoid duplicates
                            $existingAssignment = \Illuminate\Support\Facades\DB::table('sys_model_has_roles')
                                ->where('role_id', $role->id)
                                ->where('model_type', 'App\Models\User')
                                ->where('model_id', $user->id)
                                ->where('team_id', $defaultOrgId)
                                ->first();

                            if (! $existingAssignment) {
                                \Illuminate\Support\Facades\DB::table('sys_model_has_roles')->insert([
                                    'role_id' => $role->id,
                                    'model_type' => 'App\Models\User',
                                    'model_id' => $user->id,
                                    'team_id' => $defaultOrgId,
                                ]);
                            }
                        }
                    } else {
                        // Handle regular roles without teams
                        if (! $user->hasRole($roleName)) {
                            $user->assignRole($roleName);
                        }
                    }
                } catch (\Exception $e) {
                    // Log the error but continue with other assignments
                    Log::warning("Failed to assign role '{$roleName}' to user '{$email}': ".$e->getMessage());
                }
            }

            // Assign organization-specific permissions where needed
            $this->assignOrganizationSpecificPermissions($user, $assignment['organization_ids']);
        }

        // Assign board member roles specifically
        $this->assignBoardMemberRoles($adminUserId);
    }

    /**
     * Assign organization-specific permissions to users
     */
    private function assignOrganizationSpecificPermissions(User $user, array $organizationIds): void
    {
        foreach ($organizationIds as $organizationId) {
            // Set team context for organization-specific permissions
            setPermissionsTeamId($organizationId);

            // Assign organization-specific permissions based on user role context
            $userMemberships = OrganizationMembership::where('user_id', $user->id)
                ->where('organization_id', $organizationId)
                ->get();

            foreach ($userMemberships as $membership) {
                // Assign permissions based on membership type
                switch ($membership->membership_type) {
                    case 'owner':
                        $user->givePermissionTo([
                            'org:admin',
                            'org_member:admin',
                            'org_unit:admin',
                            'org_position:admin',
                            'oauth_app:admin',
                            'audit_log:admin',
                        ]);
                        break;

                    case 'admin':
                        $user->givePermissionTo([
                            'org:write',
                            'org_member:write',
                            'org_unit:write',
                            'org_position:write',
                            'oauth_app:write',
                            'audit_log:read',
                        ]);
                        break;

                    case 'manager':
                        $user->givePermissionTo([
                            'org:read',
                            'org_member:read',
                            'org_member:write',
                            'org_unit:read',
                            'org_position:read',
                            'oauth_app:read',
                        ]);
                        break;

                    case 'member':
                        $user->givePermissionTo([
                            'org:read',
                            'profile:read',
                            'profile:write',
                        ]);
                        break;

                    case 'contractor':
                    case 'guest':
                        $user->givePermissionTo([
                            'profile:read',
                            'profile:write',
                        ]);
                        break;
                }
            }
        }

        // Reset team context
        setPermissionsTeamId(null);
    }

    /**
     * Assign board member roles to users with board memberships
     */
    private function assignBoardMemberRoles($adminUserId): void
    {
        $defaultOrgId = null;
        if (config('permission.teams', false)) {
            $defaultOrg = \App\Models\Organization::where('organization_code', 'DEFAULT')->first();
            $defaultOrgId = $defaultOrg?->id;
        }

        // Find all users with board member membership type
        $boardMembers = OrganizationMembership::where('membership_type', 'board_member')
            ->with('user')
            ->get();

        foreach ($boardMembers as $membership) {
            $user = $membership->user;
            if (! $user) {
                continue;
            }

            try {
                // Try GitHub-style role names for board member role
                $boardRolesToTry = ['board-member'];

                if ($defaultOrgId) {
                    foreach ($boardRolesToTry as $roleName) {
                        $role = Role::where('name', $roleName)->where('team_id', $defaultOrgId)->first();
                        if ($role) {
                            $existingAssignment = \Illuminate\Support\Facades\DB::table('sys_model_has_roles')
                                ->where('role_id', $role->id)
                                ->where('model_type', 'App\Models\User')
                                ->where('model_id', $user->id)
                                ->where('team_id', $defaultOrgId)
                                ->first();

                            if (! $existingAssignment) {
                                \Illuminate\Support\Facades\DB::table('sys_model_has_roles')->insert([
                                    'role_id' => $role->id,
                                    'model_type' => 'App\Models\User',
                                    'model_id' => $user->id,
                                    'team_id' => $defaultOrgId,
                                ]);
                            }
                            break; // Only assign one role per board member
                        }
                    }
                } else {
                    foreach ($boardRolesToTry as $roleName) {
                        if (Role::where('name', $roleName)->exists() && ! $user->hasRole($roleName)) {
                            $user->assignRole($roleName);
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to assign board-member role to user '{$user->email}': ".$e->getMessage());
            }
        }
    }
}
