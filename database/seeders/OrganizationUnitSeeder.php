<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use Illuminate\Database\Seeder;

class OrganizationUnitSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user ID and organization IDs for created_by/updated_by
        $adminUserId = config('seeder.admin_user_id', 1);
        $orgIds = config('seeder.organization_ids');

        $units = [
            // TechCorp Holdings - Governance Units
            [
                'organization_id' => $orgIds['techcorp_holdings'],
                'unit_code' => 'BOC001',
                'name' => 'Board of Commissioners',
                'unit_type' => 'board_of_commissioners',
                'description' => 'Supervisory board responsible for oversight and strategic guidance',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Strategic oversight',
                    'Risk management oversight',
                    'Appointment of board of directors',
                    'Approval of major corporate actions',
                ],
                'authorities' => [
                    'Approve annual budget',
                    'Appoint and dismiss directors',
                    'Approve major investments',
                    'Set executive compensation',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'organization_id' => $orgIds['techcorp_holdings'],
                'unit_code' => 'BOD001',
                'name' => 'Board of Directors',
                'unit_type' => 'board_of_directors',
                'description' => 'Executive board responsible for day-to-day management',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Corporate management',
                    'Strategic execution',
                    'Financial performance',
                    'Operational oversight',
                ],
                'authorities' => [
                    'Execute business strategy',
                    'Manage operations',
                    'Make operational decisions',
                    'Report to commissioners',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'organization_id' => $orgIds['techcorp_holdings'],
                'unit_code' => 'AC001',
                'name' => 'Audit Committee',
                'unit_type' => 'audit_committee',
                'description' => 'Committee responsible for financial reporting and audit oversight',
                'parent_unit_id' => 1,
                'responsibilities' => [
                    'Financial reporting oversight',
                    'Internal audit supervision',
                    'External auditor management',
                    'Compliance monitoring',
                ],
                'authorities' => [
                    'Review financial statements',
                    'Appoint internal auditors',
                    'Review audit findings',
                    'Recommend corrective actions',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'organization_id' => $orgIds['techcorp_holdings'],
                'unit_code' => 'RC001',
                'name' => 'Risk Committee',
                'unit_type' => 'risk_committee',
                'description' => 'Committee responsible for risk management oversight',
                'parent_unit_id' => 1,
                'responsibilities' => [
                    'Risk strategy oversight',
                    'Risk appetite setting',
                    'Risk monitoring',
                    'Crisis management',
                ],
                'authorities' => [
                    'Set risk policies',
                    'Review risk reports',
                    'Approve risk limits',
                    'Escalate major risks',
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],

            // TechCorp Software - Operational Units
            [
                'organization_id' => $orgIds['techcorp_software'],
                'unit_code' => 'EXEC001',
                'name' => 'Executive Office',
                'unit_type' => 'department',
                'description' => 'Executive leadership and strategic management',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Strategic planning',
                    'Corporate governance',
                    'Stakeholder management',
                    'Executive decision making',
                ],
                'authorities' => [
                    'Set company direction',
                    'Approve major decisions',
                    'Represent company externally',
                    'Allocate resources',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'organization_id' => $orgIds['techcorp_software'],
                'unit_code' => 'ENG001',
                'name' => 'Engineering Division',
                'unit_type' => 'division',
                'description' => 'Software engineering and development',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Software development',
                    'Technical architecture',
                    'Code quality assurance',
                    'Development methodology',
                ],
                'authorities' => [
                    'Define technical standards',
                    'Approve technical designs',
                    'Manage development teams',
                    'Release software products',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'organization_id' => $orgIds['techcorp_software'],
                'unit_code' => 'FEND001',
                'name' => 'Frontend Development Team',
                'unit_type' => 'team',
                'description' => 'User interface and user experience development',
                'parent_unit_id' => 6,
                'responsibilities' => [
                    'UI/UX development',
                    'Frontend architecture',
                    'User interaction design',
                    'Frontend testing',
                ],
                'authorities' => [
                    'Choose frontend frameworks',
                    'Design user interfaces',
                    'Implement frontend features',
                    'Optimize user experience',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'organization_id' => $orgIds['techcorp_software'],
                'unit_code' => 'BEND001',
                'name' => 'Backend Development Team',
                'unit_type' => 'team',
                'description' => 'Server-side development and API services',
                'parent_unit_id' => 6,
                'responsibilities' => [
                    'API development',
                    'Database design',
                    'Server architecture',
                    'Backend testing',
                ],
                'authorities' => [
                    'Design database schemas',
                    'Implement business logic',
                    'Develop APIs',
                    'Optimize performance',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'organization_id' => $orgIds['techcorp_software'],
                'unit_code' => 'QA001',
                'name' => 'Quality Assurance Department',
                'unit_type' => 'department',
                'description' => 'Software testing and quality control',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Test planning',
                    'Test execution',
                    'Bug reporting',
                    'Quality metrics',
                ],
                'authorities' => [
                    'Approve software releases',
                    'Define testing standards',
                    'Block defective releases',
                    'Report quality metrics',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'organization_id' => $orgIds['techcorp_software'],
                'unit_code' => 'HR001',
                'name' => 'Human Resources',
                'unit_type' => 'department',
                'description' => 'Human resource management and development',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Talent acquisition',
                    'Employee development',
                    'Performance management',
                    'HR policy implementation',
                ],
                'authorities' => [
                    'Hire employees',
                    'Conduct performance reviews',
                    'Implement HR policies',
                    'Manage compensation',
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],

            // TechCorp Data - Operational Units
            [
                'organization_id' => $orgIds['techcorp_data'],
                'unit_code' => 'AI001',
                'name' => 'AI Research Division',
                'unit_type' => 'division',
                'description' => 'Artificial intelligence research and development',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'AI research',
                    'Machine learning development',
                    'Algorithm optimization',
                    'AI product development',
                ],
                'authorities' => [
                    'Conduct research projects',
                    'Develop AI models',
                    'Publish research findings',
                    'Collaborate with academia',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'organization_id' => $orgIds['techcorp_data'],
                'unit_code' => 'DATA001',
                'name' => 'Data Analytics Department',
                'unit_type' => 'department',
                'description' => 'Data analysis and business intelligence',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Data analysis',
                    'Business intelligence',
                    'Data visualization',
                    'Reporting and insights',
                ],
                'authorities' => [
                    'Access company data',
                    'Generate reports',
                    'Provide recommendations',
                    'Implement analytics solutions',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'organization_id' => $orgIds['techcorp_cloud'],
                'unit_code' => 'OPS001',
                'name' => 'Operations Department',
                'unit_type' => 'department',
                'description' => 'Cloud operations and infrastructure management',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Infrastructure monitoring',
                    'Server management',
                    'Network administration',
                    'Performance optimization',
                ],
                'authorities' => [
                    'Deploy cloud resources',
                    'Scale infrastructure',
                    'Monitor system health',
                    'Implement security patches',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'organization_id' => $orgIds['techcorp_cloud'],
                'unit_code' => 'DEV001',
                'name' => 'DevOps Team',
                'unit_type' => 'team',
                'description' => 'Development operations and automation',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'CI/CD pipeline management',
                    'Container orchestration',
                    'Infrastructure as code',
                    'Monitoring and alerting',
                ],
                'authorities' => [
                    'Configure deployment pipelines',
                    'Manage container platforms',
                    'Implement automation tools',
                    'Set up monitoring systems',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],

            // Security organization units
            [
                'organization_id' => $orgIds['techcorp_security'],
                'unit_code' => 'SEC001',
                'name' => 'Security Operations Center',
                'unit_type' => 'department',
                'description' => '24/7 security monitoring and incident response',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Security monitoring',
                    'Incident response',
                    'Threat analysis',
                    'Vulnerability management',
                ],
                'authorities' => [
                    'Investigate security incidents',
                    'Implement security controls',
                    'Block malicious traffic',
                    'Coordinate incident response',
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'organization_id' => $orgIds['techcorp_security'],
                'unit_code' => 'PEN001',
                'name' => 'Penetration Testing Team',
                'unit_type' => 'team',
                'description' => 'Ethical hacking and security assessment',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Security assessments',
                    'Penetration testing',
                    'Red team exercises',
                    'Security research',
                ],
                'authorities' => [
                    'Conduct security tests',
                    'Access target systems',
                    'Report vulnerabilities',
                    'Recommend fixes',
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'organization_id' => $orgIds['techcorp_security'],
                'unit_code' => 'COMP001',
                'name' => 'Compliance Team',
                'unit_type' => 'team',
                'description' => 'Security compliance and audit support',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Compliance monitoring',
                    'Audit support',
                    'Policy development',
                    'Risk assessment',
                ],
                'authorities' => [
                    'Conduct compliance audits',
                    'Develop security policies',
                    'Assess compliance risks',
                    'Report to regulators',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],

            // Additional support units
            [
                'organization_id' => $orgIds['techcorp_software'],
                'unit_code' => 'FIN001',
                'name' => 'Finance Department',
                'unit_type' => 'department',
                'description' => 'Financial management and accounting',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Financial planning',
                    'Budget management',
                    'Accounting operations',
                    'Financial reporting',
                ],
                'authorities' => [
                    'Approve expenditures',
                    'Manage budgets',
                    'Generate financial reports',
                    'Control cash flow',
                ],
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'organization_id' => $orgIds['techcorp_software'],
                'unit_code' => 'LEG001',
                'name' => 'Legal Department',
                'unit_type' => 'department',
                'description' => 'Legal affairs and contract management',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Contract review',
                    'Legal compliance',
                    'Intellectual property',
                    'Litigation support',
                ],
                'authorities' => [
                    'Review contracts',
                    'Provide legal advice',
                    'Manage IP portfolio',
                    'Handle legal disputes',
                ],
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'organization_id' => $orgIds['techcorp_data'],
                'unit_code' => 'RES001',
                'name' => 'Research & Development',
                'unit_type' => 'department',
                'description' => 'Advanced AI research and development',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'AI research',
                    'Algorithm development',
                    'Patent applications',
                    'Academic collaboration',
                ],
                'authorities' => [
                    'Conduct research projects',
                    'Publish research papers',
                    'File patent applications',
                    'Collaborate with universities',
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'organization_id' => $orgIds['techcorp_data'],
                'unit_code' => 'BUS001',
                'name' => 'Business Intelligence Team',
                'unit_type' => 'team',
                'description' => 'Business analytics and intelligence',
                'parent_unit_id' => null,
                'responsibilities' => [
                    'Business analytics',
                    'Market research',
                    'Performance metrics',
                    'Strategic insights',
                ],
                'authorities' => [
                    'Analyze business data',
                    'Generate insights',
                    'Create dashboards',
                    'Present findings',
                ],
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        $createdUnits = [];
        foreach ($units as $unitData) {
            $unitData['created_by'] = $adminUserId;
            $unitData['updated_by'] = $adminUserId;

            // Handle parent_unit_id references to previously created units
            if (isset($unitData['parent_unit_id']) && is_int($unitData['parent_unit_id'])) {
                // Convert 1-based index to 0-based for array access
                $parentIndex = $unitData['parent_unit_id'] - 1;
                $unitData['parent_unit_id'] = isset($createdUnits[$parentIndex]) ? $createdUnits[$parentIndex]->id : null;
            }

            $unit = OrganizationUnit::create($unitData);
            $createdUnits[] = $unit;
        }

        // Store unit IDs for use in other seeders
        config([
            'seeder.unit_ids' => [
                'board_of_commissioners' => $createdUnits[0]->id,
                'board_of_directors' => $createdUnits[1]->id,
                'audit_committee' => $createdUnits[2]->id,
                'risk_committee' => $createdUnits[3]->id,
                'executive_office' => $createdUnits[4]->id,
                'engineering_division' => $createdUnits[5]->id,
                'frontend_team' => $createdUnits[6]->id,
                'backend_team' => $createdUnits[7]->id,
                'qa_department' => $createdUnits[8]->id,
                'hr_department' => $createdUnits[9]->id,
                'ai_research_division' => $createdUnits[10]->id,
                'data_analytics_department' => $createdUnits[11]->id,
            ],
        ]);
    }
}
