<?php

namespace Database\Seeders;

use App\Models\OrganizationPosition;
use App\Models\OrganizationPositionLevel;
use Illuminate\Database\Seeder;

class OrganizationPositionSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user ID and unit IDs for created_by/updated_by
        $adminUserId = config('seeder.admin_user_id', 1);
        $unitIds = config('seeder.unit_ids');

        // Get position level IDs
        $positionLevels = OrganizationPositionLevel::pluck('id', 'code')->toArray();

        $positions = [
            // Board of Commissioners
            [
                'organization_unit_id' => $unitIds['board_of_commissioners'],
                'position_code' => 'POS001',
                'title' => 'Chairman of Board of Commissioners',
                'organization_position_level_id' => $positionLevels['board_member'],
                'job_description' => 'Lead the board of commissioners and ensure effective governance oversight',
                'qualifications' => [
                    'Minimum 15 years of executive experience',
                    'Strong leadership and governance experience',
                    'Understanding of corporate governance principles',
                    'Board certification preferred',
                ],
                'responsibilities' => [
                    'Chair board meetings',
                    'Provide strategic oversight',
                    'Ensure compliance with regulations',
                    'Evaluate board performance',
                ],
                'min_salary' => 500000.00,
                'max_salary' => 800000.00,
                'is_active' => true,
                'max_incumbents' => 1,
            ],
            [
                'organization_unit_id' => $unitIds['board_of_commissioners'],
                'position_code' => 'POS002',
                'title' => 'Commissioner',
                'organization_position_level_id' => $positionLevels['board_member'],
                'job_description' => 'Provide governance oversight and strategic guidance',
                'qualifications' => [
                    'Minimum 10 years of senior management experience',
                    'Relevant industry knowledge',
                    'Strong analytical and strategic thinking skills',
                    'Board experience preferred',
                ],
                'responsibilities' => [
                    'Participate in board meetings',
                    'Review strategic plans',
                    'Monitor risk management',
                    'Ensure regulatory compliance',
                ],
                'min_salary' => 300000.00,
                'max_salary' => 500000.00,
                'is_active' => true,
                'max_incumbents' => 4,
            ],

            // Board of Directors
            [
                'organization_unit_id' => $unitIds['board_of_directors'],
                'position_code' => 'POS003',
                'title' => 'Chief Executive Officer',
                'organization_position_level_id' => $positionLevels['c_level'],
                'job_description' => 'Lead the organization and execute strategic initiatives',
                'qualifications' => [
                    'MBA or equivalent advanced degree',
                    'Minimum 15 years of executive experience',
                    'Proven track record in technology industry',
                    'Strong leadership and communication skills',
                ],
                'responsibilities' => [
                    'Develop and execute corporate strategy',
                    'Lead executive team',
                    'Represent company to stakeholders',
                    'Drive business growth and profitability',
                ],
                'min_salary' => 800000.00,
                'max_salary' => 1500000.00,
                'is_active' => true,
                'max_incumbents' => 1,
            ],
            [
                'organization_unit_id' => $unitIds['board_of_directors'],
                'position_code' => 'POS004',
                'title' => 'Chief Financial Officer',
                'organization_position_level_id' => $positionLevels['c_level'],
                'job_description' => 'Manage financial strategy and operations',
                'qualifications' => [
                    'CPA or equivalent professional certification',
                    'Minimum 12 years of finance experience',
                    'Experience in public companies preferred',
                    'Strong analytical and strategic skills',
                ],
                'responsibilities' => [
                    'Oversee financial planning and analysis',
                    'Manage investor relations',
                    'Ensure regulatory compliance',
                    'Lead finance team',
                ],
                'min_salary' => 600000.00,
                'max_salary' => 1000000.00,
                'is_active' => true,
                'max_incumbents' => 1,
            ],

            // Executive Office
            [
                'organization_unit_id' => $unitIds['executive_office'],
                'position_code' => 'POS005',
                'title' => 'Managing Director',
                'organization_position_level_id' => $positionLevels['c_level'],
                'job_description' => 'Lead TechCorp Software operations and strategic execution',
                'qualifications' => [
                    'Advanced degree in business or technology',
                    'Minimum 12 years of software industry experience',
                    'Strong leadership and management skills',
                    'Proven track record in business growth',
                ],
                'responsibilities' => [
                    'Execute business strategy',
                    'Manage day-to-day operations',
                    'Lead management team',
                    'Drive revenue growth',
                ],
                'min_salary' => 400000.00,
                'max_salary' => 700000.00,
                'is_active' => true,
                'max_incumbents' => 1,
            ],
            [
                'organization_unit_id' => $unitIds['executive_office'],
                'position_code' => 'POS006',
                'title' => 'Chief Technology Officer',
                'organization_position_level_id' => $positionLevels['c_level'],
                'job_description' => 'Lead technology strategy and innovation',
                'qualifications' => [
                    'Computer Science or Engineering degree',
                    'Minimum 10 years of technology leadership experience',
                    'Strong technical and management skills',
                    'Experience with enterprise software development',
                ],
                'responsibilities' => [
                    'Define technology strategy',
                    'Lead engineering teams',
                    'Drive technical innovation',
                    'Ensure technology excellence',
                ],
                'min_salary' => 350000.00,
                'max_salary' => 600000.00,
                'is_active' => true,
                'max_incumbents' => 1,
            ],

            // Engineering Division
            [
                'organization_unit_id' => $unitIds['engineering_division'],
                'position_code' => 'POS007',
                'title' => 'Vice President of Engineering',
                'organization_position_level_id' => $positionLevels['vice_president'],
                'job_description' => 'Lead engineering organization and technical excellence',
                'qualifications' => [
                    'Computer Science or Engineering degree',
                    'Minimum 8 years of engineering management experience',
                    'Strong technical leadership skills',
                    'Experience scaling engineering teams',
                ],
                'responsibilities' => [
                    'Lead engineering organization',
                    'Drive technical excellence',
                    'Manage engineering teams',
                    'Define development processes',
                ],
                'min_salary' => 250000.00,
                'max_salary' => 400000.00,
                'is_active' => true,
                'max_incumbents' => 1,
            ],

            // Frontend Development Team
            [
                'organization_unit_id' => $unitIds['frontend_team'],
                'position_code' => 'POS008',
                'title' => 'Senior Frontend Developer',
                'organization_position_level_id' => $positionLevels['senior_staff'],
                'job_description' => 'Lead frontend development and mentor team members',
                'qualifications' => [
                    'Computer Science degree or equivalent experience',
                    'Minimum 5 years of frontend development experience',
                    'Expertise in React, TypeScript, and modern frontend technologies',
                    'Strong problem-solving and communication skills',
                ],
                'responsibilities' => [
                    'Develop complex frontend features',
                    'Mentor junior developers',
                    'Define frontend architecture',
                    'Ensure code quality and best practices',
                ],
                'min_salary' => 120000.00,
                'max_salary' => 180000.00,
                'is_active' => true,
                'max_incumbents' => 3,
            ],
            [
                'organization_unit_id' => $unitIds['frontend_team'],
                'position_code' => 'POS009',
                'title' => 'Frontend Developer',
                'organization_position_level_id' => $positionLevels['staff'],
                'job_description' => 'Develop and maintain frontend applications',
                'qualifications' => [
                    'Computer Science degree or equivalent experience',
                    'Minimum 2 years of frontend development experience',
                    'Proficiency in HTML, CSS, JavaScript, and React',
                    'Understanding of responsive design principles',
                ],
                'responsibilities' => [
                    'Implement UI components',
                    'Collaborate with designers and backend developers',
                    'Write clean, maintainable code',
                    'Participate in code reviews',
                ],
                'min_salary' => 80000.00,
                'max_salary' => 120000.00,
                'is_active' => true,
                'max_incumbents' => 5,
            ],

            // Backend Development Team
            [
                'organization_unit_id' => $unitIds['backend_team'],
                'position_code' => 'POS010',
                'title' => 'Senior Backend Developer',
                'organization_position_level_id' => $positionLevels['senior_staff'],
                'job_description' => 'Lead backend development and system architecture',
                'qualifications' => [
                    'Computer Science degree or equivalent experience',
                    'Minimum 5 years of backend development experience',
                    'Expertise in PHP, Laravel, databases, and API development',
                    'Strong system design and architecture skills',
                ],
                'responsibilities' => [
                    'Design and implement backend systems',
                    'Develop and maintain APIs',
                    'Optimize database performance',
                    'Mentor junior developers',
                ],
                'min_salary' => 125000.00,
                'max_salary' => 185000.00,
                'is_active' => true,
                'max_incumbents' => 3,
            ],
            [
                'organization_unit_id' => $unitIds['backend_team'],
                'position_code' => 'POS011',
                'title' => 'Backend Developer',
                'organization_position_level_id' => $positionLevels['staff'],
                'job_description' => 'Develop and maintain backend services and APIs',
                'qualifications' => [
                    'Computer Science degree or equivalent experience',
                    'Minimum 2 years of backend development experience',
                    'Proficiency in PHP, Laravel, and database technologies',
                    'Understanding of RESTful API design',
                ],
                'responsibilities' => [
                    'Implement business logic',
                    'Develop REST APIs',
                    'Write database queries',
                    'Ensure code quality and testing',
                ],
                'min_salary' => 85000.00,
                'max_salary' => 125000.00,
                'is_active' => true,
                'max_incumbents' => 5,
            ],

            // QA Department
            [
                'organization_unit_id' => $unitIds['qa_department'],
                'position_code' => 'POS012',
                'title' => 'QA Manager',
                'organization_position_level_id' => $positionLevels['manager'],
                'job_description' => 'Lead quality assurance processes and team',
                'qualifications' => [
                    'Computer Science degree or equivalent experience',
                    'Minimum 5 years of QA experience',
                    'Strong knowledge of testing methodologies',
                    'Leadership and team management skills',
                ],
                'responsibilities' => [
                    'Define testing strategies',
                    'Manage QA team',
                    'Ensure product quality',
                    'Implement QA processes',
                ],
                'min_salary' => 100000.00,
                'max_salary' => 150000.00,
                'is_active' => true,
                'max_incumbents' => 1,
            ],
            [
                'organization_unit_id' => $unitIds['qa_department'],
                'position_code' => 'POS013',
                'title' => 'QA Engineer',
                'organization_position_level_id' => $positionLevels['staff'],
                'job_description' => 'Test software applications and ensure quality',
                'qualifications' => [
                    'Computer Science degree or equivalent experience',
                    'Minimum 2 years of testing experience',
                    'Knowledge of manual and automated testing',
                    'Attention to detail and analytical skills',
                ],
                'responsibilities' => [
                    'Execute test plans',
                    'Report and track bugs',
                    'Perform functional and regression testing',
                    'Collaborate with development teams',
                ],
                'min_salary' => 70000.00,
                'max_salary' => 100000.00,
                'is_active' => true,
                'max_incumbents' => 4,
            ],

            // HR Department
            [
                'organization_unit_id' => $unitIds['hr_department'],
                'position_code' => 'POS014',
                'title' => 'HR Director',
                'organization_position_level_id' => $positionLevels['director'],
                'job_description' => 'Lead human resources strategy and operations',
                'qualifications' => [
                    'HR or Business degree',
                    'Minimum 8 years of HR experience',
                    'Strong knowledge of employment law',
                    'Leadership and strategic thinking skills',
                ],
                'responsibilities' => [
                    'Develop HR strategies',
                    'Lead HR team',
                    'Ensure compliance with labor laws',
                    'Drive employee engagement',
                ],
                'min_salary' => 120000.00,
                'max_salary' => 180000.00,
                'is_active' => true,
                'max_incumbents' => 1,
            ],
            [
                'organization_unit_id' => $unitIds['hr_department'],
                'position_code' => 'POS015',
                'title' => 'HR Specialist',
                'organization_position_level_id' => $positionLevels['staff'],
                'job_description' => 'Support HR operations and employee services',
                'qualifications' => [
                    'HR or Business degree',
                    'Minimum 2 years of HR experience',
                    'Knowledge of HR processes',
                    'Strong communication and interpersonal skills',
                ],
                'responsibilities' => [
                    'Support recruitment processes',
                    'Assist with employee onboarding',
                    'Handle employee inquiries',
                    'Maintain HR records',
                ],
                'min_salary' => 60000.00,
                'max_salary' => 85000.00,
                'is_active' => true,
                'max_incumbents' => 3,
            ],
        ];

        $createdPositions = [];
        foreach ($positions as $positionData) {
            $positionData['created_by'] = $adminUserId;
            $positionData['updated_by'] = $adminUserId;
            $position = OrganizationPosition::create($positionData);
            $createdPositions[] = $position;
        }

        // Store position IDs for use in other seeders
        config([
            'seeder.position_ids' => [
                'chairman_commissioners' => $createdPositions[0]->id,
                'commissioner' => $createdPositions[1]->id,
                'ceo' => $createdPositions[2]->id,
                'cfo' => $createdPositions[3]->id,
                'managing_director' => $createdPositions[4]->id,
                'cto' => $createdPositions[5]->id,
                'vp_engineering' => $createdPositions[6]->id,
                'senior_frontend_dev' => $createdPositions[7]->id,
                'frontend_dev' => $createdPositions[8]->id,
                'senior_backend_dev' => $createdPositions[9]->id,
                'backend_dev' => $createdPositions[10]->id,
                'qa_manager' => $createdPositions[11]->id,
                'qa_engineer' => $createdPositions[12]->id,
                'hr_director' => $createdPositions[13]->id,
                'hr_specialist' => $createdPositions[14]->id,
            ],
        ]);
    }
}
