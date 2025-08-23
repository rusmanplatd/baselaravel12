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

        $techCorpCloud = Organization::create([
            'organization_code' => 'SUB003',
            'name' => 'TechCorp Cloud',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $techCorpHoldings->id,
            'description' => 'Cloud infrastructure and services',
            'address' => '321 Cloud Way, Server City',
            'phone' => '+1-555-0400',
            'email' => 'info@techcorpcloud.com',
            'website' => 'https://techcorpcloud.com',
            'registration_number' => 'REG004',
            'tax_number' => 'TAX004',
            'governance_structure' => [
                'board_size' => 3,
                'independent_directors' => 1,
                'committees' => ['audit'],
            ],
            'authorized_capital' => 2000000.00,
            'paid_capital' => 1800000.00,
            'establishment_date' => '2022-01-01',
            'legal_status' => 'Private Limited Company',
            'business_activities' => 'Cloud hosting, infrastructure services, DevOps',
            'contact_persons' => [
                'managing_director' => ['name' => 'Chris Wilson', 'email' => 'md@techcorpcloud.com'],
                'head_of_ops' => ['name' => 'Amanda Lee', 'email' => 'ops@techcorpcloud.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $techCorpCloud->updatePath();

        $techCorpSecurity = Organization::create([
            'organization_code' => 'SUB004',
            'name' => 'TechCorp Security',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $techCorpHoldings->id,
            'description' => 'Cybersecurity and information security services',
            'address' => '555 Security Blvd, Safe Harbor',
            'phone' => '+1-555-0500',
            'email' => 'info@techcorpsecurity.com',
            'website' => 'https://techcorpsecurity.com',
            'registration_number' => 'REG005',
            'tax_number' => 'TAX005',
            'governance_structure' => [
                'board_size' => 3,
                'independent_directors' => 1,
                'committees' => ['audit', 'risk'],
            ],
            'authorized_capital' => 1500000.00,
            'paid_capital' => 1200000.00,
            'establishment_date' => '2022-03-01',
            'legal_status' => 'Private Limited Company',
            'business_activities' => 'Security auditing, penetration testing, SIEM solutions',
            'contact_persons' => [
                'managing_director' => ['name' => 'Ryan Garcia', 'email' => 'md@techcorpsecurity.com'],
                'ciso' => ['name' => 'Michelle Park', 'email' => 'ciso@techcorpsecurity.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $techCorpSecurity->updatePath();

        // Additional branch organizations
        $techCorpSoftwareEurope = Organization::create([
            'organization_code' => 'BR001',
            'name' => 'TechCorp Software Europe',
            'organization_type' => 'branch',
            'parent_organization_id' => $techCorpSoftware->id,
            'description' => 'European operations for software development',
            'address' => '10 Tech Park, London, UK',
            'phone' => '+44-20-7555-0100',
            'email' => 'europe@techcorpsoftware.com',
            'website' => 'https://techcorpsoftware.com/europe',
            'registration_number' => 'UK001',
            'tax_number' => 'UKTAX001',
            'governance_structure' => null,
            'authorized_capital' => null,
            'paid_capital' => null,
            'establishment_date' => '2023-01-01',
            'legal_status' => 'Branch Office',
            'business_activities' => 'Software development for European market',
            'contact_persons' => [
                'branch_manager' => ['name' => 'Oliver Smith', 'email' => 'oliver.smith@techcorpsoftware.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $techCorpSoftwareEurope->updatePath();

        $techCorpSoftwareAsia = Organization::create([
            'organization_code' => 'BR002',
            'name' => 'TechCorp Software Asia',
            'organization_type' => 'branch',
            'parent_organization_id' => $techCorpSoftware->id,
            'description' => 'Asian operations for software development',
            'address' => '25 Innovation Street, Singapore',
            'phone' => '+65-6555-0100',
            'email' => 'asia@techcorpsoftware.com',
            'website' => 'https://techcorpsoftware.com/asia',
            'registration_number' => 'SG001',
            'tax_number' => 'SGTAX001',
            'governance_structure' => null,
            'authorized_capital' => null,
            'paid_capital' => null,
            'establishment_date' => '2023-06-01',
            'legal_status' => 'Branch Office',
            'business_activities' => 'Software development for Asian market',
            'contact_persons' => [
                'branch_manager' => ['name' => 'Li Wei', 'email' => 'li.wei@techcorpsoftware.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $techCorpSoftwareAsia->updatePath();

        // Additional divisions
        $cloudDivision = Organization::create([
            'organization_code' => 'DIV003',
            'name' => 'Cloud Services Division',
            'organization_type' => 'division',
            'parent_organization_id' => $techCorpCloud->id,
            'description' => 'Cloud platform and infrastructure services',
            'address' => '321 Cloud Way, Server City',
            'phone' => '+1-555-0401',
            'email' => 'cloudservices@techcorpcloud.com',
            'website' => 'https://techcorpcloud.com/services',
            'registration_number' => null,
            'tax_number' => null,
            'governance_structure' => null,
            'authorized_capital' => null,
            'paid_capital' => null,
            'establishment_date' => '2022-02-01',
            'legal_status' => 'Division',
            'business_activities' => 'IaaS, PaaS, SaaS platform development',
            'contact_persons' => [
                'division_head' => ['name' => 'Tom Johnson', 'email' => 'tom.johnson@techcorpcloud.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $cloudDivision->updatePath();

        $devOpsDivision = Organization::create([
            'organization_code' => 'DIV004',
            'name' => 'DevOps Division',
            'organization_type' => 'division',
            'parent_organization_id' => $techCorpCloud->id,
            'description' => 'DevOps consulting and automation services',
            'address' => '321 Cloud Way, Server City',
            'phone' => '+1-555-0402',
            'email' => 'devops@techcorpcloud.com',
            'website' => 'https://techcorpcloud.com/devops',
            'registration_number' => null,
            'tax_number' => null,
            'governance_structure' => null,
            'authorized_capital' => null,
            'paid_capital' => null,
            'establishment_date' => '2022-04-01',
            'legal_status' => 'Division',
            'business_activities' => 'CI/CD pipeline setup, container orchestration, monitoring',
            'contact_persons' => [
                'division_head' => ['name' => 'Kevin Brown', 'email' => 'kevin.brown@techcorpcloud.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $devOpsDivision->updatePath();

        // Security divisions
        $pentestDivision = Organization::create([
            'organization_code' => 'DIV005',
            'name' => 'Penetration Testing Division',
            'organization_type' => 'division',
            'parent_organization_id' => $techCorpSecurity->id,
            'description' => 'Ethical hacking and penetration testing services',
            'address' => '555 Security Blvd, Safe Harbor',
            'phone' => '+1-555-0501',
            'email' => 'pentest@techcorpsecurity.com',
            'website' => 'https://techcorpsecurity.com/pentest',
            'registration_number' => null,
            'tax_number' => null,
            'governance_structure' => null,
            'authorized_capital' => null,
            'paid_capital' => null,
            'establishment_date' => '2022-05-01',
            'legal_status' => 'Division',
            'business_activities' => 'Security assessments, vulnerability testing, red team exercises',
            'contact_persons' => [
                'division_head' => ['name' => 'Alex Rodriguez', 'email' => 'alex.rodriguez@techcorpsecurity.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $pentestDivision->updatePath();

        $complianceDivision = Organization::create([
            'organization_code' => 'DIV006',
            'name' => 'Compliance & Audit Division',
            'organization_type' => 'division',
            'parent_organization_id' => $techCorpSecurity->id,
            'description' => 'Security compliance and audit services',
            'address' => '555 Security Blvd, Safe Harbor',
            'phone' => '+1-555-0502',
            'email' => 'compliance@techcorpsecurity.com',
            'website' => 'https://techcorpsecurity.com/compliance',
            'registration_number' => null,
            'tax_number' => null,
            'governance_structure' => null,
            'authorized_capital' => null,
            'paid_capital' => null,
            'establishment_date' => '2022-07-01',
            'legal_status' => 'Division',
            'business_activities' => 'SOC2, ISO27001, HIPAA compliance auditing',
            'contact_persons' => [
                'division_head' => ['name' => 'Sarah Kim', 'email' => 'sarah.kim@techcorpsecurity.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $complianceDivision->updatePath();

        // Store organization IDs for use in other seeders
        config([
            'seeder.organization_ids' => [
                'techcorp_holdings' => $techCorpHoldings->id,
                'techcorp_software' => $techCorpSoftware->id,
                'techcorp_data' => $techCorpData->id,
                'techcorp_cloud' => $techCorpCloud->id,
                'techcorp_security' => $techCorpSecurity->id,
                'enterprise_division' => $enterpriseDivision->id,
                'mobile_division' => $mobileDivision->id,
                'software_europe' => $techCorpSoftwareEurope->id,
                'software_asia' => $techCorpSoftwareAsia->id,
                'cloud_services_division' => $cloudDivision->id,
                'devops_division' => $devOpsDivision->id,
                'pentest_division' => $pentestDivision->id,
                'compliance_division' => $complianceDivision->id,
            ],
        ]);
    }
}
