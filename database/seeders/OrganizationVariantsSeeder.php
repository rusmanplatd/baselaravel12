<?php

namespace Database\Seeders;

use App\Models\Auth\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationVariantsSeeder extends Seeder
{
    public function run(): void
    {
        $adminUserId = config('seeder.admin_user_id', 1);

        // 1. FINANCIAL SERVICES ORGANIZATION
        $this->createFinancialServicesOrg($adminUserId);

        // 2. HEALTHCARE ORGANIZATION
        $this->createHealthcareOrg($adminUserId);

        // 3. MANUFACTURING ORGANIZATION
        $this->createManufacturingOrg($adminUserId);

        // 4. GOVERNMENT AGENCY
        $this->createGovernmentAgency($adminUserId);

        // 5. NON-PROFIT ORGANIZATION
        $this->createNonProfitOrg($adminUserId);

        // 6. EDUCATIONAL INSTITUTION
        $this->createEducationalInstitution($adminUserId);

        // 7. RETAIL CHAIN
        $this->createRetailChain($adminUserId);
    }

    private function createFinancialServicesOrg($adminUserId)
    {
        // Main financial holding company
        $financeHolding = Organization::create([
            'organization_code' => 'VFIN001',
            'name' => 'Global Finance Holdings',
            'organization_type' => 'holding_company',
            'parent_organization_id' => null,
            'description' => 'Leading financial services holding company',
            'address' => '100 Wall Street, New York, NY 10005',
            'phone' => '+1-212-555-1000',
            'email' => 'info@globalfinance.com',
            'website' => 'https://globalfinance.com',
            'registration_number' => 'FIN-REG-001',
            'tax_number' => 'FIN-TAX-001',
            'governance_structure' => [
                'board_size' => 12,
                'independent_directors' => 8,
                'committees' => ['audit', 'risk', 'nomination', 'remuneration', 'compliance'],
            ],
            'authorized_capital' => 50000000.00,
            'paid_capital' => 40000000.00,
            'establishment_date' => '2015-01-01',
            'legal_status' => 'Public Limited Company',
            'business_activities' => 'Banking, investment services, insurance',
            'contact_persons' => [
                'chairman' => ['name' => 'Robert Sterling', 'email' => 'chairman@globalfinance.com'],
                'ceo' => ['name' => 'Patricia Wells', 'email' => 'ceo@globalfinance.com'],
                'cfo' => ['name' => 'Michael Chen', 'email' => 'cfo@globalfinance.com'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $financeHolding->updatePath();

        // Commercial banking subsidiary
        $commercialBank = Organization::create([
            'organization_code' => 'VFIN002',
            'name' => 'Global Commercial Bank',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $financeHolding->id,
            'description' => 'Full-service commercial banking',
            'address' => '150 Financial District, New York, NY 10006',
            'phone' => '+1-212-555-2000',
            'email' => 'info@globalcommercialbank.com',
            'website' => 'https://globalcommercialbank.com',
            'registration_number' => 'BANK-001',
            'tax_number' => 'BANK-TAX-001',
            'establishment_date' => '2015-06-01',
            'legal_status' => 'Banking Corporation',
            'business_activities' => 'Commercial banking, lending, deposit services',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $commercialBank->updatePath();

        // Investment management subsidiary
        $investmentMgmt = Organization::create([
            'organization_code' => 'VFIN003',
            'name' => 'Global Investment Management',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $financeHolding->id,
            'description' => 'Asset management and investment services',
            'address' => '200 Investment Plaza, Boston, MA 02101',
            'phone' => '+1-617-555-3000',
            'email' => 'info@globalinvestment.com',
            'website' => 'https://globalinvestment.com',
            'establishment_date' => '2016-01-01',
            'legal_status' => 'Investment Advisory Company',
            'business_activities' => 'Asset management, portfolio management, investment advisory',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $investmentMgmt->updatePath();

        // Create finance-specific roles and users
        $this->createFinanceRolesAndUsers($financeHolding, $commercialBank, $investmentMgmt, $adminUserId);
    }

    private function createHealthcareOrg($adminUserId)
    {
        // Healthcare system
        $healthcareSystem = Organization::create([
            'organization_code' => 'VHC001',
            'name' => 'Regional Healthcare System',
            'organization_type' => 'holding_company',
            'parent_organization_id' => null,
            'description' => 'Integrated healthcare delivery system',
            'address' => '500 Medical Center Drive, Chicago, IL 60611',
            'phone' => '+1-312-555-5000',
            'email' => 'info@regionalhealthcare.org',
            'website' => 'https://regionalhealthcare.org',
            'registration_number' => 'HC-REG-001',
            'tax_number' => 'HC-TAX-001',
            'establishment_date' => '2010-01-01',
            'legal_status' => 'Non-Profit Healthcare System',
            'business_activities' => 'Healthcare delivery, medical education, research',
            'contact_persons' => [
                'ceo' => ['name' => 'Dr. Maria Rodriguez', 'email' => 'ceo@regionalhealthcare.org'],
                'cmo' => ['name' => 'Dr. James Patterson', 'email' => 'cmo@regionalhealthcare.org'],
            ],
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $healthcareSystem->updatePath();

        // Main hospital
        $mainHospital = Organization::create([
            'organization_code' => 'VHC002',
            'name' => 'Regional Medical Center',
            'organization_type' => 'subsidiary',
            'parent_organization_id' => $healthcareSystem->id,
            'description' => 'Tertiary care academic medical center',
            'address' => '500 Medical Center Drive, Chicago, IL 60611',
            'phone' => '+1-312-555-5100',
            'email' => 'info@regionalmedcenter.org',
            'establishment_date' => '2010-01-01',
            'legal_status' => 'Hospital',
            'business_activities' => 'Inpatient care, emergency services, specialty care',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $mainHospital->updatePath();

        // Community clinic
        $communitClinic = Organization::create([
            'organization_code' => 'VHC003',
            'name' => 'Community Health Clinic',
            'organization_type' => 'branch',
            'parent_organization_id' => $healthcareSystem->id,
            'description' => 'Primary care and preventive services',
            'address' => '1200 Community Way, Chicago, IL 60612',
            'phone' => '+1-312-555-5200',
            'email' => 'info@communityhealthclinic.org',
            'establishment_date' => '2012-01-01',
            'legal_status' => 'Clinic',
            'business_activities' => 'Primary care, preventive medicine, community health',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $communitClinic->updatePath();

        $this->createHealthcareRolesAndUsers($healthcareSystem, $mainHospital, $communitClinic, $adminUserId);
    }

    private function createManufacturingOrg($adminUserId)
    {
        // Manufacturing conglomerate
        $mfgConglomerate = Organization::create([
            'organization_code' => 'VMFG001',
            'name' => 'Advanced Manufacturing Group',
            'organization_type' => 'holding_company',
            'parent_organization_id' => null,
            'description' => 'Diversified manufacturing conglomerate',
            'address' => '2000 Industrial Boulevard, Detroit, MI 48201',
            'phone' => '+1-313-555-7000',
            'email' => 'info@advancedmfg.com',
            'website' => 'https://advancedmfg.com',
            'establishment_date' => '2005-01-01',
            'legal_status' => 'Manufacturing Corporation',
            'business_activities' => 'Automotive parts, aerospace components, industrial equipment',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $mfgConglomerate->updatePath();

        // Automotive division
        $automotivePlant = Organization::create([
            'organization_code' => 'VMFG002',
            'name' => 'Automotive Components Division',
            'organization_type' => 'division',
            'parent_organization_id' => $mfgConglomerate->id,
            'description' => 'Precision automotive components manufacturing',
            'address' => '2000 Industrial Boulevard, Detroit, MI 48201',
            'phone' => '+1-313-555-7100',
            'email' => 'automotive@advancedmfg.com',
            'establishment_date' => '2005-01-01',
            'legal_status' => 'Manufacturing Division',
            'business_activities' => 'Engine parts, transmission components, brake systems',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $automotivePlant->updatePath();

        $this->createManufacturingRolesAndUsers($mfgConglomerate, $automotivePlant, $adminUserId);
    }

    private function createGovernmentAgency($adminUserId)
    {
        // Government department
        $govDept = Organization::create([
            'organization_code' => 'VGOV001',
            'name' => 'Department of Digital Services',
            'organization_type' => 'department',
            'parent_organization_id' => null,
            'description' => 'State government digital transformation agency',
            'address' => '1 Government Plaza, Sacramento, CA 95814',
            'phone' => '+1-916-555-8000',
            'email' => 'info@digitalservices.ca.gov',
            'website' => 'https://digitalservices.ca.gov',
            'establishment_date' => '2020-01-01',
            'legal_status' => 'Government Agency',
            'business_activities' => 'Digital government services, IT infrastructure, citizen services',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $govDept->updatePath();

        $this->createGovernmentRolesAndUsers($govDept, $adminUserId);
    }

    private function createNonProfitOrg($adminUserId)
    {
        // Non-profit foundation
        $foundation = Organization::create([
            'organization_code' => 'VNPO001',
            'name' => 'Community Development Foundation',
            'organization_type' => 'unit',
            'parent_organization_id' => null,
            'description' => 'Community development and social services',
            'address' => '300 Nonprofit Way, Austin, TX 78701',
            'phone' => '+1-512-555-9000',
            'email' => 'info@communityfoundation.org',
            'website' => 'https://communityfoundation.org',
            'establishment_date' => '2008-01-01',
            'legal_status' => '501(c)(3) Non-Profit',
            'business_activities' => 'Community development, education programs, social services',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $foundation->updatePath();

        $this->createNonProfitRolesAndUsers($foundation, $adminUserId);
    }

    private function createEducationalInstitution($adminUserId)
    {
        // University
        $university = Organization::create([
            'organization_code' => 'VEDU001',
            'name' => 'Metropolitan State University',
            'organization_type' => 'unit',
            'parent_organization_id' => null,
            'description' => 'Public research university',
            'address' => '1000 University Drive, Denver, CO 80204',
            'phone' => '+1-303-555-2000',
            'email' => 'info@metrostate.edu',
            'website' => 'https://metrostate.edu',
            'establishment_date' => '1965-01-01',
            'legal_status' => 'Public University',
            'business_activities' => 'Higher education, research, community service',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $university->updatePath();

        $this->createEducationalRolesAndUsers($university, $adminUserId);
    }

    private function createRetailChain($adminUserId)
    {
        // Retail headquarters
        $retailHQ = Organization::create([
            'organization_code' => 'VRTL001',
            'name' => 'Metro Retail Chain',
            'organization_type' => 'holding_company',
            'parent_organization_id' => null,
            'description' => 'Regional retail chain headquarters',
            'address' => '5000 Commerce Center, Atlanta, GA 30309',
            'phone' => '+1-404-555-6000',
            'email' => 'info@metroretail.com',
            'website' => 'https://metroretail.com',
            'establishment_date' => '1995-01-01',
            'legal_status' => 'Retail Corporation',
            'business_activities' => 'Retail operations, merchandising, supply chain',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $retailHQ->updatePath();

        // Regional stores
        $retailRegion = Organization::create([
            'organization_code' => 'VRTL002',
            'name' => 'Southeast Region',
            'organization_type' => 'division',
            'parent_organization_id' => $retailHQ->id,
            'description' => 'Southeast regional operations',
            'address' => '5000 Commerce Center, Atlanta, GA 30309',
            'phone' => '+1-404-555-6100',
            'email' => 'southeast@metroretail.com',
            'establishment_date' => '1995-01-01',
            'legal_status' => 'Regional Division',
            'business_activities' => 'Store operations, regional management',
            'is_active' => true,
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);
        $retailRegion->updatePath();

        $this->createRetailRolesAndUsers($retailHQ, $retailRegion, $adminUserId);
    }

    // Role and user creation methods for each organization type
    private function createFinanceRolesAndUsers($holding, $bank, $investment, $adminUserId)
    {
        // Create finance-specific roles
        $bankingManagerRole = Role::firstOrCreate([
            'name' => 'Banking Manager',
            'guard_name' => 'web',
            'team_id' => $bank->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $loanOfficerRole = Role::firstOrCreate([
            'name' => 'Loan Officer',
            'guard_name' => 'web',
            'team_id' => $bank->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $portfolioManagerRole = Role::firstOrCreate([
            'name' => 'Portfolio Manager',
            'guard_name' => 'web',
            'team_id' => $investment->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        // Create users
        $bankManager = User::factory()->create([
            'name' => 'Jennifer Banks',
            'email' => 'j.banks@globalcommercialbank.com',
        ]);

        $loanOfficer = User::factory()->create([
            'name' => 'Thomas Credit',
            'email' => 't.credit@globalcommercialbank.com',
        ]);

        $portfolioMgr = User::factory()->create([
            'name' => 'Alexandra Portfolio',
            'email' => 'a.portfolio@globalinvestment.com',
        ]);

        // Assign roles
        DB::table('sys_model_has_roles')->insert([
            ['role_id' => $bankingManagerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $bankManager->id, 'team_id' => $bank->id],
            ['role_id' => $loanOfficerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $loanOfficer->id, 'team_id' => $bank->id],
            ['role_id' => $portfolioManagerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $portfolioMgr->id, 'team_id' => $investment->id],
        ]);
    }

    private function createHealthcareRolesAndUsers($system, $hospital, $clinic, $adminUserId)
    {
        $chiefMedicalOfficerRole = Role::firstOrCreate([
            'name' => 'Chief Medical Officer',
            'guard_name' => 'web',
            'team_id' => $system->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $attendingPhysicianRole = Role::firstOrCreate([
            'name' => 'Attending Physician',
            'guard_name' => 'web',
            'team_id' => $hospital->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $nurseManagerRole = Role::firstOrCreate([
            'name' => 'Nurse Manager',
            'guard_name' => 'web',
            'team_id' => $hospital->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        // Create healthcare users
        $cmo = User::factory()->create([
            'name' => 'Dr. Sarah Medical',
            'email' => 'dr.medical@regionalhealthcare.org',
        ]);

        $physician = User::factory()->create([
            'name' => 'Dr. John Attending',
            'email' => 'dr.attending@regionalmedcenter.org',
        ]);

        $nurseManager = User::factory()->create([
            'name' => 'Mary Nurse',
            'email' => 'm.nurse@regionalmedcenter.org',
        ]);

        // Assign roles
        DB::table('sys_model_has_roles')->insert([
            ['role_id' => $chiefMedicalOfficerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $cmo->id, 'team_id' => $system->id],
            ['role_id' => $attendingPhysicianRole->id, 'model_type' => 'App\Models\User', 'model_id' => $physician->id, 'team_id' => $hospital->id],
            ['role_id' => $nurseManagerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $nurseManager->id, 'team_id' => $hospital->id],
        ]);
    }

    private function createManufacturingRolesAndUsers($conglomerate, $plant, $adminUserId)
    {
        $plantManagerRole = Role::firstOrCreate([
            'name' => 'Plant Manager',
            'guard_name' => 'web',
            'team_id' => $plant->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $qualityManagerRole = Role::firstOrCreate([
            'name' => 'Quality Manager',
            'guard_name' => 'web',
            'team_id' => $plant->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $productionSupervisorRole = Role::firstOrCreate([
            'name' => 'Production Supervisor',
            'guard_name' => 'web',
            'team_id' => $plant->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        // Create manufacturing users
        $plantMgr = User::factory()->create([
            'name' => 'Robert Plant',
            'email' => 'r.plant@advancedmfg.com',
        ]);

        $qualityMgr = User::factory()->create([
            'name' => 'Lisa Quality',
            'email' => 'l.quality@advancedmfg.com',
        ]);

        $supervisor = User::factory()->create([
            'name' => 'Mike Production',
            'email' => 'm.production@advancedmfg.com',
        ]);

        // Assign roles
        DB::table('sys_model_has_roles')->insert([
            ['role_id' => $plantManagerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $plantMgr->id, 'team_id' => $plant->id],
            ['role_id' => $qualityManagerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $qualityMgr->id, 'team_id' => $plant->id],
            ['role_id' => $productionSupervisorRole->id, 'model_type' => 'App\Models\User', 'model_id' => $supervisor->id, 'team_id' => $plant->id],
        ]);
    }

    private function createGovernmentRolesAndUsers($dept, $adminUserId)
    {
        $directorRole = Role::firstOrCreate([
            'name' => 'Department Director',
            'guard_name' => 'web',
            'team_id' => $dept->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $programManagerRole = Role::firstOrCreate([
            'name' => 'Program Manager',
            'guard_name' => 'web',
            'team_id' => $dept->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $civilServantRole = Role::firstOrCreate([
            'name' => 'Civil Servant',
            'guard_name' => 'web',
            'team_id' => $dept->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        // Create government users
        $director = User::factory()->create([
            'name' => 'Patricia Director',
            'email' => 'p.director@digitalservices.ca.gov',
        ]);

        $programMgr = User::factory()->create([
            'name' => 'James Program',
            'email' => 'j.program@digitalservices.ca.gov',
        ]);

        $civilServant = User::factory()->create([
            'name' => 'Angela Service',
            'email' => 'a.service@digitalservices.ca.gov',
        ]);

        // Assign roles
        DB::table('sys_model_has_roles')->insert([
            ['role_id' => $directorRole->id, 'model_type' => 'App\Models\User', 'model_id' => $director->id, 'team_id' => $dept->id],
            ['role_id' => $programManagerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $programMgr->id, 'team_id' => $dept->id],
            ['role_id' => $civilServantRole->id, 'model_type' => 'App\Models\User', 'model_id' => $civilServant->id, 'team_id' => $dept->id],
        ]);
    }

    private function createNonProfitRolesAndUsers($foundation, $adminUserId)
    {
        $executiveDirectorRole = Role::firstOrCreate([
            'name' => 'Executive Director',
            'guard_name' => 'web',
            'team_id' => $foundation->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $programCoordinatorRole = Role::firstOrCreate([
            'name' => 'Program Coordinator',
            'guard_name' => 'web',
            'team_id' => $foundation->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $volunteerRole = Role::firstOrCreate([
            'name' => 'Volunteer',
            'guard_name' => 'web',
            'team_id' => $foundation->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        // Create nonprofit users
        $execDirector = User::factory()->create([
            'name' => 'Maria Executive',
            'email' => 'm.executive@communityfoundation.org',
        ]);

        $coordinator = User::factory()->create([
            'name' => 'David Coordinator',
            'email' => 'd.coordinator@communityfoundation.org',
        ]);

        $volunteer = User::factory()->create([
            'name' => 'Susan Volunteer',
            'email' => 's.volunteer@communityfoundation.org',
        ]);

        // Assign roles
        DB::table('sys_model_has_roles')->insert([
            ['role_id' => $executiveDirectorRole->id, 'model_type' => 'App\Models\User', 'model_id' => $execDirector->id, 'team_id' => $foundation->id],
            ['role_id' => $programCoordinatorRole->id, 'model_type' => 'App\Models\User', 'model_id' => $coordinator->id, 'team_id' => $foundation->id],
            ['role_id' => $volunteerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $volunteer->id, 'team_id' => $foundation->id],
        ]);
    }

    private function createEducationalRolesAndUsers($university, $adminUserId)
    {
        $presidentRole = Role::firstOrCreate([
            'name' => 'University President',
            'guard_name' => 'web',
            'team_id' => $university->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $deanRole = Role::firstOrCreate([
            'name' => 'Dean',
            'guard_name' => 'web',
            'team_id' => $university->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $professorRole = Role::firstOrCreate([
            'name' => 'Professor',
            'guard_name' => 'web',
            'team_id' => $university->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $studentRole = Role::firstOrCreate([
            'name' => 'Student',
            'guard_name' => 'web',
            'team_id' => $university->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        // Create educational users
        $president = User::factory()->create([
            'name' => 'Dr. Richard President',
            'email' => 'dr.president@metrostate.edu',
        ]);

        $dean = User::factory()->create([
            'name' => 'Dr. Linda Dean',
            'email' => 'dr.dean@metrostate.edu',
        ]);

        $professor = User::factory()->create([
            'name' => 'Dr. Michael Professor',
            'email' => 'dr.professor@metrostate.edu',
        ]);

        $student = User::factory()->create([
            'name' => 'Emily Student',
            'email' => 'e.student@student.metrostate.edu',
        ]);

        // Assign roles
        DB::table('sys_model_has_roles')->insert([
            ['role_id' => $presidentRole->id, 'model_type' => 'App\Models\User', 'model_id' => $president->id, 'team_id' => $university->id],
            ['role_id' => $deanRole->id, 'model_type' => 'App\Models\User', 'model_id' => $dean->id, 'team_id' => $university->id],
            ['role_id' => $professorRole->id, 'model_type' => 'App\Models\User', 'model_id' => $professor->id, 'team_id' => $university->id],
            ['role_id' => $studentRole->id, 'model_type' => 'App\Models\User', 'model_id' => $student->id, 'team_id' => $university->id],
        ]);
    }

    private function createRetailRolesAndUsers($hq, $region, $adminUserId)
    {
        $regionalManagerRole = Role::firstOrCreate([
            'name' => 'Regional Manager',
            'guard_name' => 'web',
            'team_id' => $region->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $storeManagerRole = Role::firstOrCreate([
            'name' => 'Store Manager',
            'guard_name' => 'web',
            'team_id' => $region->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        $salesAssociateRole = Role::firstOrCreate([
            'name' => 'Sales Associate',
            'guard_name' => 'web',
            'team_id' => $region->id,
        ], [
            'created_by' => $adminUserId,
            'updated_by' => $adminUserId,
        ]);

        // Create retail users
        $regionalMgr = User::factory()->create([
            'name' => 'Carol Regional',
            'email' => 'c.regional@metroretail.com',
        ]);

        $storeMgr = User::factory()->create([
            'name' => 'Kevin Store',
            'email' => 'k.store@metroretail.com',
        ]);

        $salesAssoc = User::factory()->create([
            'name' => 'Ashley Sales',
            'email' => 'a.sales@metroretail.com',
        ]);

        // Assign roles
        DB::table('sys_model_has_roles')->insert([
            ['role_id' => $regionalManagerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $regionalMgr->id, 'team_id' => $region->id],
            ['role_id' => $storeManagerRole->id, 'model_type' => 'App\Models\User', 'model_id' => $storeMgr->id, 'team_id' => $region->id],
            ['role_id' => $salesAssociateRole->id, 'model_type' => 'App\Models\User', 'model_id' => $salesAssoc->id, 'team_id' => $region->id],
        ]);
    }
}
