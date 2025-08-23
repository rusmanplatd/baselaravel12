<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user ID for created_by/updated_by
        $adminUserId = config('seeder.admin_user_id', 1);

        // Create parent organizations first
        $techCorpHoldings = Organization::create([
            'organization_code' => 'HC001',
            'name' => 'TechCorp Holdings',
            'organization_type' => 'holding_company',
            'parent_organization_id' => null,
            'description' => 'Main holding company for technology businesses',
            'address' => '123 Tech Street, Innovation District',
            'phone' => '+1-555-0100',
            'email' => 'info@techcorp.com',
            'website' => 'https://techcorp.com',
            'registration_number' => 'REG001',
            'tax_number' => 'TAX001',
            'governance_structure' => [
                'board_size' => 7,
                'independent_directors' => 4,
                'committees' => ['audit', 'risk', 'nomination', 'remuneration'],
            ],
            'authorized_capital' => 10000000.00,
            'paid_capital' => 8500000.00,
            'establishment_date' => '2020-01-15',
            'legal_status' => 'Public Limited Company',
            'business_activities' => 'Investment holding and management',
            'contact_persons' => [
                'ceo' => ['name' => 'John Smith', 'email' => 'ceo@techcorp.com'],
                'cfo' => ['name' => 'Jane Doe', 'email' => 'cfo@techcorp.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $techCorpHoldings->updatePath();

        // Create subsidiary organizations
        $techCorpSoftware = Organization::create([
            'organization_code' => 'SUB001',
            'name' => 'TechCorp Software',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $techCorpHoldings->id,
            'description' => 'Software development and consulting services',
            'address' => '456 Software Ave, Tech City',
            'phone' => '+1-555-0200',
            'email' => 'info@techcorpsoftware.com',
            'website' => 'https://techcorpsoftware.com',
            'registration_number' => 'REG002',
            'tax_number' => 'TAX002',
            'governance_structure' => [
                'board_size' => 5,
                'independent_directors' => 2,
                'committees' => ['audit', 'risk'],
            ],
            'authorized_capital' => 5000000.00,
            'paid_capital' => 4000000.00,
            'establishment_date' => '2021-03-01',
            'legal_status' => 'Private Limited Company',
            'business_activities' => 'Software development, web applications, mobile apps',
            'contact_persons' => [
                'managing_director' => ['name' => 'Mike Johnson', 'email' => 'md@techcorpsoftware.com'],
                'cto' => ['name' => 'Sarah Wilson', 'email' => 'cto@techcorpsoftware.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $techCorpSoftware->updatePath();

        $techCorpData = Organization::create([
            'organization_code' => 'SUB002',
            'name' => 'TechCorp Data',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $techCorpHoldings->id,
            'description' => 'Data analytics and AI solutions',
            'address' => '789 Data Drive, Analytics Park',
            'phone' => '+1-555-0300',
            'email' => 'info@techcorpdata.com',
            'website' => 'https://techcorpdata.com',
            'registration_number' => 'REG003',
            'tax_number' => 'TAX003',
            'governance_structure' => [
                'board_size' => 5,
                'independent_directors' => 2,
                'committees' => ['audit', 'risk'],
            ],
            'authorized_capital' => 3000000.00,
            'paid_capital' => 2500000.00,
            'establishment_date' => '2021-06-15',
            'legal_status' => 'Private Limited Company',
            'business_activities' => 'Data analytics, machine learning, AI consulting',
            'contact_persons' => [
                'managing_director' => ['name' => 'David Brown', 'email' => 'md@techcorpdata.com'],
                'head_of_ai' => ['name' => 'Emily Davis', 'email' => 'ai@techcorpdata.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $techCorpData->updatePath();

        // Create division organizations
        $enterpriseDivision = Organization::create([
            'organization_code' => 'DIV001',
            'name' => 'Enterprise Solutions Division',
            'organization_type' => 'division',
            'parent_organization_id' => $techCorpSoftware->id,
            'description' => 'Enterprise software solutions and services',
            'address' => '456 Software Ave, Tech City',
            'phone' => '+1-555-0201',
            'email' => 'enterprise@techcorpsoftware.com',
            'website' => 'https://techcorpsoftware.com/enterprise',
            'registration_number' => null,
            'tax_number' => null,
            'governance_structure' => null,
            'authorized_capital' => null,
            'paid_capital' => null,
            'establishment_date' => '2022-01-01',
            'legal_status' => 'Division',
            'business_activities' => 'Enterprise software development and implementation',
            'contact_persons' => [
                'division_head' => ['name' => 'Robert Taylor', 'email' => 'robert.taylor@techcorpsoftware.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $enterpriseDivision->updatePath();

        $mobileDivision = Organization::create([
            'organization_code' => 'DIV002',
            'name' => 'Mobile Solutions Division',
            'organization_type' => 'division',
            'parent_organization_id' => $techCorpSoftware->id,
            'description' => 'Mobile application development and services',
            'address' => '456 Software Ave, Tech City',
            'phone' => '+1-555-0202',
            'email' => 'mobile@techcorpsoftware.com',
            'website' => 'https://techcorpsoftware.com/mobile',
            'registration_number' => null,
            'tax_number' => null,
            'governance_structure' => null,
            'authorized_capital' => null,
            'paid_capital' => null,
            'establishment_date' => '2022-01-01',
            'legal_status' => 'Division',
            'business_activities' => 'iOS and Android application development',
            'contact_persons' => [
                'division_head' => ['name' => 'Lisa Anderson', 'email' => 'lisa.anderson@techcorpsoftware.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $mobileDivision->updatePath();

        // Store organization IDs for use in other seeders
        config([
            'seeder.organization_ids' => [
                'techcorp_holdings' => $techCorpHoldings->id,
                'techcorp_software' => $techCorpSoftware->id,
                'techcorp_data' => $techCorpData->id,
                'enterprise_division' => $enterpriseDivision->id,
                'mobile_division' => $mobileDivision->id,
            ],
        ]);
    }
}
