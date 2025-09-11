<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class IndustrySpecificPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Create system user if it doesn't exist and set auth context
        $systemUser = User::firstOrCreate([
            'email' => 'system@system.local',
        ], [
            'name' => 'System User',
            'email' => 'system@system.local',
            'password' => Hash::make('system-user-password-'.bin2hex(random_bytes(16))),
            'email_verified_at' => now(),
        ]);

        Auth::login($systemUser);

        // Industry-specific permissions (GitHub style: resource:action)
        $industryPermissions = [
            // Financial Services Permissions
            'accounts:read' => 'View financial accounts',
            'accounts:write' => 'Create and edit financial accounts',
            'accounts:delete' => 'Close financial accounts',
            'transactions:read' => 'View transactions',
            'transactions:approve' => 'Approve transactions',
            'loans:read' => 'View loan applications',
            'loans:write' => 'Process loan applications',
            'loans:approve' => 'Approve loans',
            'compliance:read' => 'View compliance reports',
            'compliance:audit' => 'Conduct financial audits',
            'portfolio:read' => 'View investment portfolios',
            'portfolio:write' => 'Manage investment portfolios',
            'risk:assess' => 'Assess financial risks',

            // Healthcare Permissions
            'patients:read' => 'View patient information',
            'patients:write' => 'Edit patient information',
            'medical-records:read' => 'View medical records',
            'medical-records:write' => 'Edit medical records',
            'prescriptions:read' => 'View prescriptions',
            'prescriptions:write' => 'Create prescriptions',
            'appointments:read' => 'View appointments',
            'appointments:write' => 'Schedule appointments',
            'billing:read' => 'View medical billing',
            'billing:write' => 'Process medical billing',
            'lab-results:read' => 'View lab results',
            'lab-results:approve' => 'Approve lab results',
            'emergency:access' => 'Emergency medical access',
            'quality:review' => 'Review quality metrics',

            // Manufacturing Permissions
            'production:read' => 'View production data',
            'production:control' => 'Control production processes',
            'quality:inspect' => 'Conduct quality inspections',
            'quality:approve' => 'Approve quality standards',
            'inventory:read' => 'View inventory levels',
            'inventory:write' => 'Manage inventory',
            'maintenance:schedule' => 'Schedule equipment maintenance',
            'maintenance:approve' => 'Approve maintenance work',
            'safety:monitor' => 'Monitor safety compliance',
            'safety:incident' => 'Report safety incidents',
            'supply-chain:read' => 'View supply chain data',
            'supply-chain:write' => 'Manage supply chain',

            // Government Permissions
            'citizens:read' => 'View citizen information',
            'citizens:services' => 'Provide citizen services',
            'documents:public' => 'Access public documents',
            'documents:classified' => 'Access classified documents',
            'permits:read' => 'View permit applications',
            'permits:write' => 'Process permit applications',
            'permits:approve' => 'Approve permits',
            'regulations:read' => 'View regulations',
            'regulations:enforce' => 'Enforce regulations',
            'budget:read' => 'View budget information',
            'budget:write' => 'Manage budget allocations',
            'procurement:read' => 'View procurement requests',
            'procurement:approve' => 'Approve procurement',

            // Non-Profit Permissions
            'donors:read' => 'View donor information',
            'donors:write' => 'Manage donor relationships',
            'donations:read' => 'View donation records',
            'donations:write' => 'Process donations',
            'grants:read' => 'View grant applications',
            'grants:write' => 'Manage grant applications',
            'programs:read' => 'View program information',
            'programs:write' => 'Manage programs',
            'volunteers:read' => 'View volunteer information',
            'volunteers:coordinate' => 'Coordinate volunteers',
            'impact:read' => 'View impact reports',
            'impact:write' => 'Create impact reports',

            // Educational Permissions
            'students:read' => 'View student information',
            'students:write' => 'Edit student records',
            'grades:read' => 'View grades',
            'grades:write' => 'Enter grades',
            'courses:read' => 'View course information',
            'courses:write' => 'Manage courses',
            'curriculum:read' => 'View curriculum',
            'curriculum:write' => 'Develop curriculum',
            'admissions:read' => 'View admissions',
            'admissions:write' => 'Process admissions',
            'faculty:read' => 'View faculty information',
            'faculty:write' => 'Manage faculty',
            'research:read' => 'View research projects',
            'research:write' => 'Conduct research',

            // Retail Permissions
            'products:read' => 'View product information',
            'products:write' => 'Manage products',
            'retail-inventory:read' => 'View retail inventory',
            'retail-inventory:write' => 'Manage retail inventory',
            'sales:read' => 'View sales data',
            'sales:write' => 'Process sales transactions',
            'customers:read' => 'View customer information',
            'customers:write' => 'Manage customer relationships',
            'pricing:read' => 'View pricing information',
            'pricing:write' => 'Manage pricing',
            'promotions:read' => 'View promotions',
            'promotions:write' => 'Create promotions',
            'returns:read' => 'View return requests',
            'returns:write' => 'Process returns',

            // Project Management Permissions
            'projects:read' => 'View projects',
            'projects:write' => 'Create and edit projects',
            'projects:delete' => 'Delete projects',
            'projects:admin' => 'Administer projects',
            'project-members:read' => 'View project members',
            'project-members:write' => 'Add and remove project members',
            'project-members:admin' => 'Manage member roles',
            'project-items:read' => 'View project items',
            'project-items:write' => 'Create and edit project items',
            'project-items:delete' => 'Delete project items',
            'project-items:assign' => 'Assign project items',
            'project-views:read' => 'View project views',
            'project-views:write' => 'Create and edit project views',
            'project-views:delete' => 'Delete project views',
            'project-fields:read' => 'View project fields',
            'project-fields:write' => 'Create and edit custom fields',
            'project-fields:delete' => 'Delete custom fields',
            'project-workflows:read' => 'View project workflows',
            'project-workflows:write' => 'Create and edit project workflows',
            'project-workflows:delete' => 'Delete project workflows',
        ];

        // Create industry-specific permissions
        foreach ($industryPermissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $this->assignIndustryPermissionsToRoles();

        Auth::logout();
    }

    private function assignIndustryPermissionsToRoles()
    {
        $this->assignGlobalRolePermissions();
        $this->assignScopedRolePermissions();
    }

    private function assignGlobalRolePermissions()
    {
        // Financial Services Role Permissions (GitHub style: kebab-case)
        $financialRoles = [
            'banking-manager' => [
                'accounts:read',
                'accounts:write',
                'transactions:read',
                'transactions:approve',
                'compliance:read',
                'risk:assess',
            ],
            'loan-officer' => [
                'accounts:read',
                'loans:read',
                'loans:write',
                'transactions:read',
            ],
            'portfolio-manager' => [
                'portfolio:read',
                'portfolio:write',
                'risk:assess',
                'compliance:read',
            ],
        ];

        // Healthcare Role Permissions
        $healthcareRoles = [
            'chief-medical-officer' => [
                'patients:read',
                'medical-records:read',
                'quality:review',
                'emergency:access',
            ],
            'attending-physician' => [
                'patients:read',
                'patients:write',
                'medical-records:read',
                'medical-records:write',
                'prescriptions:read',
                'prescriptions:write',
                'appointments:read',
                'appointments:write',
                'lab-results:read',
                'emergency:access',
            ],
            'nurse-manager' => [
                'patients:read',
                'medical-records:read',
                'appointments:read',
                'appointments:write',
                'lab-results:read',
            ],
        ];

        // Manufacturing Role Permissions
        $manufacturingRoles = [
            'plant-manager' => [
                'production:read',
                'production:control',
                'quality:approve',
                'inventory:read',
                'inventory:write',
                'maintenance:approve',
                'safety:monitor',
                'supply-chain:read',
                'supply-chain:write',
            ],
            'quality-manager' => [
                'quality:inspect',
                'quality:approve',
                'production:read',
                'safety:monitor',
            ],
            'production-supervisor' => [
                'production:read',
                'production:control',
                'quality:inspect',
                'inventory:read',
                'maintenance:schedule',
                'safety:monitor',
                'safety:incident',
            ],
        ];

        // Government Role Permissions
        $governmentRoles = [
            'department-director' => [
                'citizens:read',
                'citizens:services',
                'documents:public',
                'permits:approve',
                'regulations:read',
                'regulations:enforce',
                'budget:read',
                'budget:write',
                'procurement:approve',
            ],
            'program-manager' => [
                'citizens:read',
                'citizens:services',
                'documents:public',
                'permits:read',
                'permits:write',
                'regulations:read',
                'budget:read',
                'procurement:read',
            ],
            'civil-servant' => [
                'citizens:read',
                'citizens:services',
                'documents:public',
                'permits:read',
                'regulations:read',
            ],
        ];

        // Non-Profit Role Permissions
        $nonProfitRoles = [
            'executive-director' => [
                'donors:read',
                'donors:write',
                'donations:read',
                'grants:read',
                'grants:write',
                'programs:read',
                'programs:write',
                'volunteers:read',
                'volunteers:coordinate',
                'impact:read',
                'impact:write',
            ],
            'program-coordinator' => [
                'programs:read',
                'programs:write',
                'volunteers:read',
                'volunteers:coordinate',
                'impact:read',
            ],
            'volunteer' => [
                'programs:read',
                'volunteers:read',
            ],
        ];

        // Educational Role Permissions
        $educationalRoles = [
            'university-president' => [
                'students:read',
                'courses:read',
                'curriculum:read',
                'admissions:read',
                'faculty:read',
                'faculty:write',
                'research:read',
            ],
            'dean' => [
                'students:read',
                'courses:read',
                'courses:write',
                'curriculum:read',
                'curriculum:write',
                'faculty:read',
                'faculty:write',
                'research:read',
                'research:write',
            ],
            'professor' => [
                'students:read',
                'students:write',
                'grades:read',
                'grades:write',
                'courses:read',
                'curriculum:read',
                'research:read',
                'research:write',
            ],
            'student' => [
                'courses:read',
                'grades:read',
            ],
        ];

        // Retail Role Permissions
        $retailRoles = [
            'regional-manager' => [
                'products:read',
                'products:write',
                'retail-inventory:read',
                'retail-inventory:write',
                'sales:read',
                'customers:read',
                'customers:write',
                'pricing:read',
                'pricing:write',
                'promotions:read',
                'promotions:write',
                'returns:read',
                'returns:write',
            ],
            'store-manager' => [
                'products:read',
                'retail-inventory:read',
                'retail-inventory:write',
                'sales:read',
                'sales:write',
                'customers:read',
                'pricing:read',
                'promotions:read',
                'returns:read',
                'returns:write',
            ],
            'sales-associate' => [
                'products:read',
                'retail-inventory:read',
                'sales:read',
                'sales:write',
                'customers:read',
                'pricing:read',
                'returns:read',
                'returns:write',
            ],
        ];

        // Project Management Role Permissions
        $projectRoles = [
            'project-manager' => [
                'projects:read',
                'projects:write',
                'projects:delete',
                'projects:admin',
                'project-members:read',
                'project-members:write',
                'project-members:admin',
                'project-items:read',
                'project-items:write',
                'project-items:delete',
                'project-items:assign',
                'project-views:read',
                'project-views:write',
                'project-views:delete',
                'project-fields:read',
                'project-fields:write',
                'project-fields:delete',
                'project-workflows:read',
                'project-workflows:write',
                'project-workflows:delete',
            ],
            'team-lead' => [
                'projects:read',
                'projects:write',
                'project-members:read',
                'project-members:write',
                'project-items:read',
                'project-items:write',
                'project-items:assign',
                'project-views:read',
                'project-views:write',
                'project-fields:read',
                'project-workflows:read',
            ],
            'developer' => [
                'projects:read',
                'project-members:read',
                'project-items:read',
                'project-items:write',
                'project-views:read',
                'project-fields:read',
                'project-workflows:read',
            ],
            'designer' => [
                'projects:read',
                'project-members:read',
                'project-items:read',
                'project-items:write',
                'project-views:read',
                'project-fields:read',
                'project-workflows:read',
            ],
            'stakeholder' => [
                'projects:read',
                'project-members:read',
                'project-items:read',
                'project-views:read',
                'project-fields:read',
            ],
        ];

        $allRolePermissions = array_merge(
            $financialRoles,
            $healthcareRoles,
            $manufacturingRoles,
            $governmentRoles,
            $nonProfitRoles,
            $educationalRoles,
            $retailRoles,
            $projectRoles
        );

        foreach ($allRolePermissions as $roleName => $permissions) {
            $roles = Role::where('name', $roleName)->get();

            foreach ($roles as $role) {
                $permissionObjects = Permission::whereIn('name', $permissions)->get();
                $role->syncPermissions($permissionObjects->pluck('name')->toArray());
            }
        }
    }

    private function assignScopedRolePermissions()
    {
        // Get sample users and organizations for scoped examples
        $user = User::where('email', '!=', 'system@system.local')->first();
        if (!$user) {
            return; // No user available for scoped demo
        }

        // Get sample organizations (assuming they exist from other seeders)
        $organizations = \App\Models\Organization::limit(2)->get();
        if ($organizations->isEmpty()) {
            return; // No organizations available
        }

        $organization1 = $organizations->first();
        $organization2 = $organizations->count() > 1 ? $organizations->last() : $organization1;

        // Example 1: Assign global role (no scope)
        $user->assignRole('super-admin');

        // Example 2: Assign scoped roles to different organizations
        $user->assignRole('banking-manager', $organization1);
        $user->assignRole('project-manager', $organization2);

        // Example 3: Give scoped permissions
        $user->givePermissionTo('accounts:read', $organization1);
        $user->givePermissionTo('projects:admin', $organization2);

        $this->command->info("Assigned scoped roles and permissions:");
        $this->command->info("- Global: super-admin (includes global chat permissions)");
        $this->command->info("- {$organization1->name}: banking-manager + accounts:read");
        $this->command->info("- {$organization2->name}: project-manager + projects:admin");
        $this->command->info("- Note: Chat permissions are global and automatically granted to all authenticated users");
    }
}
