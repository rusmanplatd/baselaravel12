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

        // Industry-specific permissions
        $industryPermissions = [
            // Financial Services Permissions
            'financial.accounts.view' => 'View financial accounts',
            'financial.accounts.create' => 'Create financial accounts',
            'financial.accounts.edit' => 'Edit financial accounts',
            'financial.accounts.close' => 'Close financial accounts',
            'financial.transactions.view' => 'View transactions',
            'financial.transactions.approve' => 'Approve transactions',
            'financial.loans.view' => 'View loan applications',
            'financial.loans.process' => 'Process loan applications',
            'financial.loans.approve' => 'Approve loans',
            'financial.compliance.view' => 'View compliance reports',
            'financial.compliance.audit' => 'Conduct financial audits',
            'financial.portfolio.view' => 'View investment portfolios',
            'financial.portfolio.manage' => 'Manage investment portfolios',
            'financial.risk.assess' => 'Assess financial risks',

            // Healthcare Permissions
            'healthcare.patients.view' => 'View patient information',
            'healthcare.patients.edit' => 'Edit patient information',
            'healthcare.medical_records.view' => 'View medical records',
            'healthcare.medical_records.edit' => 'Edit medical records',
            'healthcare.prescriptions.view' => 'View prescriptions',
            'healthcare.prescriptions.create' => 'Create prescriptions',
            'healthcare.appointments.view' => 'View appointments',
            'healthcare.appointments.schedule' => 'Schedule appointments',
            'healthcare.billing.view' => 'View medical billing',
            'healthcare.billing.process' => 'Process medical billing',
            'healthcare.lab_results.view' => 'View lab results',
            'healthcare.lab_results.approve' => 'Approve lab results',
            'healthcare.emergency.access' => 'Emergency medical access',
            'healthcare.quality.review' => 'Review quality metrics',

            // Manufacturing Permissions
            'manufacturing.production.view' => 'View production data',
            'manufacturing.production.control' => 'Control production processes',
            'manufacturing.quality.inspect' => 'Conduct quality inspections',
            'manufacturing.quality.approve' => 'Approve quality standards',
            'manufacturing.inventory.view' => 'View inventory levels',
            'manufacturing.inventory.manage' => 'Manage inventory',
            'manufacturing.maintenance.schedule' => 'Schedule equipment maintenance',
            'manufacturing.maintenance.approve' => 'Approve maintenance work',
            'manufacturing.safety.monitor' => 'Monitor safety compliance',
            'manufacturing.safety.incident' => 'Report safety incidents',
            'manufacturing.supply_chain.view' => 'View supply chain data',
            'manufacturing.supply_chain.manage' => 'Manage supply chain',

            // Government Permissions
            'government.citizens.view' => 'View citizen information',
            'government.citizens.services' => 'Provide citizen services',
            'government.documents.public' => 'Access public documents',
            'government.documents.classified' => 'Access classified documents',
            'government.permits.view' => 'View permit applications',
            'government.permits.process' => 'Process permit applications',
            'government.permits.approve' => 'Approve permits',
            'government.regulations.view' => 'View regulations',
            'government.regulations.enforce' => 'Enforce regulations',
            'government.budget.view' => 'View budget information',
            'government.budget.manage' => 'Manage budget allocations',
            'government.procurement.view' => 'View procurement requests',
            'government.procurement.approve' => 'Approve procurement',

            // Non-Profit Permissions
            'nonprofit.donors.view' => 'View donor information',
            'nonprofit.donors.manage' => 'Manage donor relationships',
            'nonprofit.donations.view' => 'View donation records',
            'nonprofit.donations.process' => 'Process donations',
            'nonprofit.grants.view' => 'View grant applications',
            'nonprofit.grants.manage' => 'Manage grant applications',
            'nonprofit.programs.view' => 'View program information',
            'nonprofit.programs.manage' => 'Manage programs',
            'nonprofit.volunteers.view' => 'View volunteer information',
            'nonprofit.volunteers.coordinate' => 'Coordinate volunteers',
            'nonprofit.impact.view' => 'View impact reports',
            'nonprofit.impact.create' => 'Create impact reports',

            // Educational Permissions
            'education.students.view' => 'View student information',
            'education.students.edit' => 'Edit student records',
            'education.grades.view' => 'View grades',
            'education.grades.enter' => 'Enter grades',
            'education.courses.view' => 'View course information',
            'education.courses.manage' => 'Manage courses',
            'education.curriculum.view' => 'View curriculum',
            'education.curriculum.develop' => 'Develop curriculum',
            'education.admissions.view' => 'View admissions',
            'education.admissions.process' => 'Process admissions',
            'education.faculty.view' => 'View faculty information',
            'education.faculty.manage' => 'Manage faculty',
            'education.research.view' => 'View research projects',
            'education.research.conduct' => 'Conduct research',

            // Retail Permissions
            'retail.products.view' => 'View product information',
            'retail.products.manage' => 'Manage products',
            'retail.inventory.view' => 'View retail inventory',
            'retail.inventory.manage' => 'Manage retail inventory',
            'retail.sales.view' => 'View sales data',
            'retail.sales.process' => 'Process sales transactions',
            'retail.customers.view' => 'View customer information',
            'retail.customers.manage' => 'Manage customer relationships',
            'retail.pricing.view' => 'View pricing information',
            'retail.pricing.manage' => 'Manage pricing',
            'retail.promotions.view' => 'View promotions',
            'retail.promotions.create' => 'Create promotions',
            'retail.returns.view' => 'View return requests',
            'retail.returns.process' => 'Process returns',

            // Project Management Permissions
            'projects.view' => 'View projects',
            'projects.create' => 'Create new projects',
            'projects.edit' => 'Edit projects',
            'projects.delete' => 'Delete projects',
            'projects.admin' => 'Administer projects',
            'projects.members.view' => 'View project members',
            'projects.members.add' => 'Add project members',
            'projects.members.remove' => 'Remove project members',
            'projects.members.manage' => 'Manage member roles',
            'projects.items.view' => 'View project items',
            'projects.items.create' => 'Create project items',
            'projects.items.edit' => 'Edit project items',
            'projects.items.delete' => 'Delete project items',
            'projects.items.assign' => 'Assign project items',
            'projects.views.view' => 'View project views',
            'projects.views.create' => 'Create project views',
            'projects.views.edit' => 'Edit project views',
            'projects.views.delete' => 'Delete project views',
            'projects.fields.view' => 'View project fields',
            'projects.fields.create' => 'Create custom fields',
            'projects.fields.edit' => 'Edit custom fields',
            'projects.fields.delete' => 'Delete custom fields',
            'projects.workflows.view' => 'View project workflows',
            'projects.workflows.create' => 'Create project workflows',
            'projects.workflows.edit' => 'Edit project workflows',
            'projects.workflows.delete' => 'Delete project workflows',
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
        // Financial Services Role Permissions
        $financialRoles = [
            'Banking Manager' => [
                'financial.accounts.view',
                'financial.accounts.create',
                'financial.accounts.edit',
                'financial.transactions.view',
                'financial.transactions.approve',
                'financial.compliance.view',
                'financial.risk.assess',
            ],
            'Loan Officer' => [
                'financial.accounts.view',
                'financial.loans.view',
                'financial.loans.process',
                'financial.transactions.view',
            ],
            'Portfolio Manager' => [
                'financial.portfolio.view',
                'financial.portfolio.manage',
                'financial.risk.assess',
                'financial.compliance.view',
            ],
        ];

        // Healthcare Role Permissions
        $healthcareRoles = [
            'Chief Medical Officer' => [
                'healthcare.patients.view',
                'healthcare.medical_records.view',
                'healthcare.quality.review',
                'healthcare.emergency.access',
            ],
            'Attending Physician' => [
                'healthcare.patients.view',
                'healthcare.patients.edit',
                'healthcare.medical_records.view',
                'healthcare.medical_records.edit',
                'healthcare.prescriptions.view',
                'healthcare.prescriptions.create',
                'healthcare.appointments.view',
                'healthcare.appointments.schedule',
                'healthcare.lab_results.view',
                'healthcare.emergency.access',
            ],
            'Nurse Manager' => [
                'healthcare.patients.view',
                'healthcare.medical_records.view',
                'healthcare.appointments.view',
                'healthcare.appointments.schedule',
                'healthcare.lab_results.view',
            ],
        ];

        // Manufacturing Role Permissions
        $manufacturingRoles = [
            'Plant Manager' => [
                'manufacturing.production.view',
                'manufacturing.production.control',
                'manufacturing.quality.approve',
                'manufacturing.inventory.view',
                'manufacturing.inventory.manage',
                'manufacturing.maintenance.approve',
                'manufacturing.safety.monitor',
                'manufacturing.supply_chain.view',
                'manufacturing.supply_chain.manage',
            ],
            'Quality Manager' => [
                'manufacturing.quality.inspect',
                'manufacturing.quality.approve',
                'manufacturing.production.view',
                'manufacturing.safety.monitor',
            ],
            'Production Supervisor' => [
                'manufacturing.production.view',
                'manufacturing.production.control',
                'manufacturing.quality.inspect',
                'manufacturing.inventory.view',
                'manufacturing.maintenance.schedule',
                'manufacturing.safety.monitor',
                'manufacturing.safety.incident',
            ],
        ];

        // Government Role Permissions
        $governmentRoles = [
            'Department Director' => [
                'government.citizens.view',
                'government.citizens.services',
                'government.documents.public',
                'government.permits.approve',
                'government.regulations.view',
                'government.regulations.enforce',
                'government.budget.view',
                'government.budget.manage',
                'government.procurement.approve',
            ],
            'Program Manager' => [
                'government.citizens.view',
                'government.citizens.services',
                'government.documents.public',
                'government.permits.view',
                'government.permits.process',
                'government.regulations.view',
                'government.budget.view',
                'government.procurement.view',
            ],
            'Civil Servant' => [
                'government.citizens.view',
                'government.citizens.services',
                'government.documents.public',
                'government.permits.view',
                'government.regulations.view',
            ],
        ];

        // Non-Profit Role Permissions
        $nonProfitRoles = [
            'Executive Director' => [
                'nonprofit.donors.view',
                'nonprofit.donors.manage',
                'nonprofit.donations.view',
                'nonprofit.grants.view',
                'nonprofit.grants.manage',
                'nonprofit.programs.view',
                'nonprofit.programs.manage',
                'nonprofit.volunteers.view',
                'nonprofit.volunteers.coordinate',
                'nonprofit.impact.view',
                'nonprofit.impact.create',
            ],
            'Program Coordinator' => [
                'nonprofit.programs.view',
                'nonprofit.programs.manage',
                'nonprofit.volunteers.view',
                'nonprofit.volunteers.coordinate',
                'nonprofit.impact.view',
            ],
            'Volunteer' => [
                'nonprofit.programs.view',
                'nonprofit.volunteers.view',
            ],
        ];

        // Educational Role Permissions
        $educationalRoles = [
            'University President' => [
                'education.students.view',
                'education.courses.view',
                'education.curriculum.view',
                'education.admissions.view',
                'education.faculty.view',
                'education.faculty.manage',
                'education.research.view',
            ],
            'Dean' => [
                'education.students.view',
                'education.courses.view',
                'education.courses.manage',
                'education.curriculum.view',
                'education.curriculum.develop',
                'education.faculty.view',
                'education.faculty.manage',
                'education.research.view',
                'education.research.conduct',
            ],
            'Professor' => [
                'education.students.view',
                'education.students.edit',
                'education.grades.view',
                'education.grades.enter',
                'education.courses.view',
                'education.curriculum.view',
                'education.research.view',
                'education.research.conduct',
            ],
            'Student' => [
                'education.courses.view',
                'education.grades.view',
            ],
        ];

        // Retail Role Permissions
        $retailRoles = [
            'Regional Manager' => [
                'retail.products.view',
                'retail.products.manage',
                'retail.inventory.view',
                'retail.inventory.manage',
                'retail.sales.view',
                'retail.customers.view',
                'retail.customers.manage',
                'retail.pricing.view',
                'retail.pricing.manage',
                'retail.promotions.view',
                'retail.promotions.create',
                'retail.returns.view',
                'retail.returns.process',
            ],
            'Store Manager' => [
                'retail.products.view',
                'retail.inventory.view',
                'retail.inventory.manage',
                'retail.sales.view',
                'retail.sales.process',
                'retail.customers.view',
                'retail.pricing.view',
                'retail.promotions.view',
                'retail.returns.view',
                'retail.returns.process',
            ],
            'Sales Associate' => [
                'retail.products.view',
                'retail.inventory.view',
                'retail.sales.view',
                'retail.sales.process',
                'retail.customers.view',
                'retail.pricing.view',
                'retail.returns.view',
                'retail.returns.process',
            ],
        ];

        // Project Management Role Permissions
        $projectRoles = [
            'Project Manager' => [
                'projects.view',
                'projects.create',
                'projects.edit',
                'projects.admin',
                'projects.members.view',
                'projects.members.add',
                'projects.members.remove',
                'projects.members.manage',
                'projects.items.view',
                'projects.items.create',
                'projects.items.edit',
                'projects.items.delete',
                'projects.items.assign',
                'projects.views.view',
                'projects.views.create',
                'projects.views.edit',
                'projects.views.delete',
                'projects.fields.view',
                'projects.fields.create',
                'projects.fields.edit',
                'projects.fields.delete',
                'projects.workflows.view',
                'projects.workflows.create',
                'projects.workflows.edit',
                'projects.workflows.delete',
            ],
            'Team Lead' => [
                'projects.view',
                'projects.edit',
                'projects.members.view',
                'projects.members.add',
                'projects.items.view',
                'projects.items.create',
                'projects.items.edit',
                'projects.items.assign',
                'projects.views.view',
                'projects.views.create',
                'projects.views.edit',
                'projects.fields.view',
                'projects.workflows.view',
            ],
            'Developer' => [
                'projects.view',
                'projects.members.view',
                'projects.items.view',
                'projects.items.create',
                'projects.items.edit',
                'projects.views.view',
                'projects.fields.view',
                'projects.workflows.view',
            ],
            'Designer' => [
                'projects.view',
                'projects.members.view',
                'projects.items.view',
                'projects.items.create',
                'projects.items.edit',
                'projects.views.view',
                'projects.fields.view',
                'projects.workflows.view',
            ],
            'Stakeholder' => [
                'projects.view',
                'projects.members.view',
                'projects.items.view',
                'projects.views.view',
                'projects.fields.view',
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
}
