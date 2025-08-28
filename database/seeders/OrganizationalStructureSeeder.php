<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationalStructureSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user ID for created_by/updated_by
        $adminUserId = config('seeder.admin_user_id', 1);

        // Create Holding Company
        $holdingCompany = Organization::create([
            'organization_code' => 'HC-001',
            'name' => 'Global Tech Holdings Ltd',
            'organization_type' => 'holding_company',
            'description' => 'Technology holding company with multiple subsidiaries',
            'address' => '123 Business District, Tech City',
            'phone' => '+1-555-0001',
            'email' => 'info@globaltech.com',
            'website' => 'https://globaltech.com',
            'registration_number' => 'REG-HC-2020-001',
            'tax_number' => 'TAX-HC-001',
            'authorized_capital' => 1000000000.00,
            'paid_capital' => 800000000.00,
            'establishment_date' => '2020-01-15',
            'legal_status' => 'Public Limited Company',
            'business_activities' => 'Technology investment and holding company operations',
            'governance_structure' => [
                'board_size' => 7,
                'independent_directors' => 4,
                'executive_directors' => 3,
            ],
            'contact_persons' => [
                [
                    'name' => 'John Smith',
                    'position' => 'Company Secretary',
                    'email' => 'secretary@globaltech.com',
                ],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $holdingCompany->updatePath();

        // Create Subsidiaries
        $techSub = Organization::create([
            'organization_code' => 'SUB-001',
            'name' => 'TechSolutions Inc',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $holdingCompany->id,
            'description' => 'Software development and IT solutions',
            'address' => '456 Innovation Blvd, Tech City',
            'phone' => '+1-555-0002',
            'email' => 'info@techsolutions.com',
            'website' => 'https://techsolutions.com',
            'registration_number' => 'REG-SUB-2020-001',
            'tax_number' => 'TAX-SUB-001',
            'authorized_capital' => 50000000.00,
            'paid_capital' => 45000000.00,
            'establishment_date' => '2020-03-01',
            'legal_status' => 'Private Limited Company',
            'business_activities' => 'Software development, cloud services, IT consulting',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $techSub->updatePath();

        $finSub = Organization::create([
            'organization_code' => 'SUB-002',
            'name' => 'FinTech Innovations Ltd',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $holdingCompany->id,
            'description' => 'Financial technology and digital banking solutions',
            'address' => '789 Finance Street, Tech City',
            'phone' => '+1-555-0003',
            'email' => 'info@fintech-innovations.com',
            'website' => 'https://fintech-innovations.com',
            'registration_number' => 'REG-SUB-2021-001',
            'tax_number' => 'TAX-SUB-002',
            'authorized_capital' => 75000000.00,
            'paid_capital' => 60000000.00,
            'establishment_date' => '2021-06-15',
            'legal_status' => 'Private Limited Company',
            'business_activities' => 'Digital banking, payment processing, financial software',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $finSub->updatePath();

        // Helper function to add audit fields
        $addAuditFields = function ($data) use ($adminUserId) {
            return array_merge($data, [
                'created_by' => $adminUserId,
                'updated_by' => $adminUserId,
            ]);
        };

        // Create Board of Commissioners for Holding Company
        $boc = OrganizationUnit::create($addAuditFields([
            'organization_id' => $holdingCompany->id,
            'unit_code' => 'BOC-HC-001',
            'name' => 'Board of Commissioners',
            'unit_type' => 'board_of_commissioners',
            'description' => 'Supervisory board responsible for oversight and strategic guidance',
            'responsibilities' => [
                'Strategic oversight',
                'Risk management supervision',
                'Executive performance evaluation',
                'Corporate governance compliance',
            ],
            'authorities' => [
                'Approve strategic plans',
                'Appoint/remove board of directors',
                'Approve major transactions',
                'Set executive compensation',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]));

        // Create Board of Directors for Holding Company
        $bod = OrganizationUnit::create($addAuditFields([
            'organization_id' => $holdingCompany->id,
            'unit_code' => 'BOD-HC-001',
            'name' => 'Board of Directors',
            'unit_type' => 'board_of_directors',
            'description' => 'Executive board responsible for day-to-day management and operations',
            'responsibilities' => [
                'Daily operations management',
                'Strategy implementation',
                'Business development',
                'Operational oversight',
            ],
            'authorities' => [
                'Execute business strategy',
                'Manage operations',
                'Make operational decisions',
                'Hire/fire executives',
            ],
            'is_active' => true,
            'sort_order' => 2,
        ]));

        // Create Audit Committee
        $auditCommittee = OrganizationUnit::create($addAuditFields([
            'organization_id' => $holdingCompany->id,
            'unit_code' => 'AC-HC-001',
            'name' => 'Audit Committee',
            'unit_type' => 'audit_committee',
            'description' => 'Committee responsible for financial reporting and audit oversight',
            'parent_unit_id' => $boc->id,
            'responsibilities' => [
                'Financial reporting oversight',
                'Internal audit supervision',
                'External auditor management',
                'Compliance monitoring',
            ],
            'authorities' => [
                'Review financial statements',
                'Appoint external auditors',
                'Oversee internal controls',
                'Investigate financial irregularities',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]));

        // Create divisions for TechSolutions
        $engineeringDiv = OrganizationUnit::create($addAuditFields([
            'organization_id' => $techSub->id,
            'unit_code' => 'ENG-TS-001',
            'name' => 'Engineering Division',
            'unit_type' => 'division',
            'description' => 'Software engineering and product development',
            'responsibilities' => [
                'Software development',
                'Product architecture',
                'Technical innovation',
                'Quality assurance',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]));

        $salesDiv = OrganizationUnit::create($addAuditFields([
            'organization_id' => $techSub->id,
            'unit_code' => 'SALES-TS-001',
            'name' => 'Sales & Marketing Division',
            'unit_type' => 'division',
            'description' => 'Sales operations and marketing activities',
            'responsibilities' => [
                'Customer acquisition',
                'Revenue generation',
                'Marketing campaigns',
                'Customer relationship management',
            ],
            'is_active' => true,
            'sort_order' => 2,
        ]));

        // Create positions
        $boardMemberLevel = \App\Models\OrganizationPositionLevel::where('code', 'board_member')->first();
        $cLevelLevel = \App\Models\OrganizationPositionLevel::where('code', 'c_level')->first();
        $vpLevel = \App\Models\OrganizationPositionLevel::where('code', 'vice_president')->first();

        $chairmanPos = OrganizationPosition::create($addAuditFields([
            'organization_id' => $holdingCompany->id,
            'organization_unit_id' => $boc->id,
            'position_code' => 'POS-CHAIR-001',
            'title' => 'Chairman of Board of Commissioners',
            'organization_position_level_id' => $boardMemberLevel->id,
            'job_description' => 'Lead the board of commissioners and provide strategic oversight',
            'qualifications' => [
                'Minimum 15 years senior management experience',
                'Strong corporate governance background',
                'Strategic leadership skills',
            ],
            'responsibilities' => [
                'Chair board meetings',
                'Represent shareholders',
                'Ensure governance compliance',
                'Guide strategic direction',
            ],
            'max_incumbents' => 1,
            'is_active' => true,
        ]));

        $ceoPos = OrganizationPosition::create($addAuditFields([
            'organization_id' => $holdingCompany->id,
            'organization_unit_id' => $bod->id,
            'position_code' => 'POS-CEO-001',
            'title' => 'Chief Executive Officer',
            'organization_position_level_id' => $cLevelLevel->id,
            'job_description' => 'Lead the organization and execute strategic plans',
            'qualifications' => [
                'MBA or equivalent',
                'Minimum 10 years executive experience',
                'Proven leadership track record',
            ],
            'responsibilities' => [
                'Strategic execution',
                'Organizational leadership',
                'Stakeholder management',
                'Performance accountability',
            ],
            'min_salary' => 300000.00,
            'max_salary' => 500000.00,
            'max_incumbents' => 1,
            'is_active' => true,
        ]));

        $ctoPos = OrganizationPosition::create($addAuditFields([
            'organization_id' => $techSub->id,
            'organization_unit_id' => $engineeringDiv->id,
            'position_code' => 'POS-CTO-001',
            'title' => 'Chief Technology Officer',
            'organization_position_level_id' => $cLevelLevel->id,
            'job_description' => 'Lead technology strategy and engineering operations',
            'qualifications' => [
                'Computer Science degree',
                'Minimum 10 years tech leadership',
                'Strong technical architecture skills',
            ],
            'responsibilities' => [
                'Technology strategy',
                'Engineering leadership',
                'Technical architecture',
                'Innovation management',
            ],
            'min_salary' => 250000.00,
            'max_salary' => 400000.00,
            'max_incumbents' => 1,
            'is_active' => true,
        ]));

        $vpSalesPos = OrganizationPosition::create($addAuditFields([
            'organization_id' => $techSub->id,
            'organization_unit_id' => $salesDiv->id,
            'position_code' => 'POS-VP-SALES-001',
            'title' => 'Vice President of Sales',
            'organization_position_level_id' => $vpLevel->id,
            'job_description' => 'Lead sales operations and revenue generation',
            'qualifications' => [
                'Business or Marketing degree',
                'Minimum 8 years sales leadership',
                'Proven revenue growth track record',
            ],
            'responsibilities' => [
                'Sales strategy',
                'Revenue targets',
                'Team leadership',
                'Customer relationships',
            ],
            'min_salary' => 150000.00,
            'max_salary' => 250000.00,
            'max_incumbents' => 1,
            'is_active' => true,
        ]));

        // Create sample users
        $user1 = User::firstOrCreate(['email' => 'chairman@globaltech.com'], $addAuditFields([
            'name' => 'Robert Johnson',
            'password' => Hash::make('password'),
        ]));

        $user2 = User::firstOrCreate(['email' => 'ceo@globaltech.com'], $addAuditFields([
            'name' => 'Sarah Williams',
            'password' => Hash::make('password'),
        ]));

        $user3 = User::firstOrCreate(['email' => 'cto@techsolutions.com'], $addAuditFields([
            'name' => 'Michael Chen',
            'password' => Hash::make('password'),
        ]));

        $user4 = User::firstOrCreate(['email' => 'vpsales@techsolutions.com'], $addAuditFields([
            'name' => 'Jennifer Davis',
            'password' => Hash::make('password'),
        ]));

        // Create memberships
        OrganizationMembership::create($addAuditFields([
            'user_id' => $user1->id,
            'organization_id' => $holdingCompany->id,
            'organization_unit_id' => $boc->id,
            'organization_position_id' => $chairmanPos->id,
            'membership_type' => 'board_member',
            'start_date' => '2020-01-15',
            'status' => 'active',
        ]));

        OrganizationMembership::create($addAuditFields([
            'user_id' => $user2->id,
            'organization_id' => $holdingCompany->id,
            'organization_unit_id' => $bod->id,
            'organization_position_id' => $ceoPos->id,
            'membership_type' => 'board_member',
            'start_date' => '2020-02-01',
            'status' => 'active',
        ]));

        OrganizationMembership::create($addAuditFields([
            'user_id' => $user3->id,
            'organization_id' => $techSub->id,
            'organization_unit_id' => $engineeringDiv->id,
            'organization_position_id' => $ctoPos->id,
            'membership_type' => 'employee',
            'start_date' => '2020-03-15',
            'status' => 'active',
        ]));

        OrganizationMembership::create($addAuditFields([
            'user_id' => $user4->id,
            'organization_id' => $techSub->id,
            'organization_unit_id' => $salesDiv->id,
            'organization_position_id' => $vpSalesPos->id,
            'membership_type' => 'employee',
            'start_date' => '2020-04-01',
            'status' => 'active',
        ]));

        $this->command->info('Organizational structure seeded successfully!');
        $this->command->info('- 1 Holding Company (Global Tech Holdings Ltd)');
        $this->command->info('- 2 Subsidiaries (TechSolutions Inc, FinTech Innovations Ltd)');
        $this->command->info('- 6 Organizational Units (BOC, BOD, Audit Committee, 2 Divisions)');
        $this->command->info('- 4 Positions (Chairman, CEO, CTO, VP Sales)');
        $this->command->info('- 4 Users with active memberships');
    }
}
