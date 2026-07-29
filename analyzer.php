<?php

class ResumeAnalyzer {
    // Extensive list of skills categorized for mapping
    private static $skillsDictionary = [
        // Programming Languages & Web Core
        'php' => 'PHP',
        'javascript' => 'JavaScript',
        'js' => 'JavaScript',
        'typescript' => 'TypeScript',
        'ts' => 'TypeScript',
        'python' => 'Python',
        'java' => 'Java',
        'c++' => 'C++',
        'c#' => 'C#',
        'ruby' => 'Ruby',
        'go' => 'Go',
        'golang' => 'Go',
        'rust' => 'Rust',
        'swift' => 'Swift',
        'kotlin' => 'Kotlin',
        'html' => 'HTML5',
        'html5' => 'HTML5',
        'css' => 'CSS3',
        'css3' => 'CSS3',
        'sass' => 'Sass',
        'sql' => 'SQL',
        
        // Frameworks & Libraries
        'laravel' => 'Laravel',
        'symfony' => 'Symfony',
        'codeigniter' => 'CodeIgniter',
        'yii' => 'Yii',
        'react' => 'React',
        'react.js' => 'React',
        'reactjs' => 'React',
        'angular' => 'Angular',
        'angularjs' => 'Angular',
        'vue' => 'Vue.js',
        'vuejs' => 'Vue.js',
        'next.js' => 'Next.js',
        'nextjs' => 'Next.js',
        'node' => 'Node.js',
        'node.js' => 'Node.js',
        'nodejs' => 'Node.js',
        'express' => 'Express.js',
        'expressjs' => 'Express.js',
        'django' => 'Django',
        'flask' => 'Flask',
        'spring' => 'Spring Boot',
        'spring boot' => 'Spring Boot',
        'asp.net' => 'ASP.NET',
        'jquery' => 'jQuery',
        'bootstrap' => 'Bootstrap',
        'tailwind' => 'Tailwind CSS',
        
        // Databases & Caching
        'mysql' => 'MySQL',
        'postgresql' => 'PostgreSQL',
        'postgres' => 'PostgreSQL',
        'sqlite' => 'SQLite',
        'mongodb' => 'MongoDB',
        'mongo' => 'MongoDB',
        'redis' => 'Redis',
        'mariadb' => 'MariaDB',
        'oracle' => 'Oracle Database',
        'dynamodb' => 'DynamoDB',
        'cassandra' => 'Cassandra',
        'elasticsearch' => 'Elasticsearch',

        // DevOps, Cloud & Tools
        'aws' => 'AWS',
        'amazon web services' => 'AWS',
        'azure' => 'Azure',
        'gcp' => 'Google Cloud Platform',
        'google cloud' => 'Google Cloud Platform',
        'docker' => 'Docker',
        'kubernetes' => 'Kubernetes',
        'k8s' => 'Kubernetes',
        'jenkins' => 'Jenkins',
        'github actions' => 'GitHub Actions',
        'git' => 'Git',
        'github' => 'GitHub',
        'gitlab' => 'GitLab',
        'bitbucket' => 'BitBucket',
        'linux' => 'Linux',
        'apache' => 'Apache',
        'nginx' => 'Nginx',
        'ansible' => 'Ansible',
        'terraform' => 'Terraform',
        'ci/cd' => 'CI/CD',
        'cicd' => 'CI/CD',

        // Core Concepts & Methodologies
        'rest' => 'RESTful APIs',
        'restful' => 'RESTful APIs',
        'api' => 'API Development',
        'graphql' => 'GraphQL',
        'soap' => 'SOAP',
        'mvc' => 'MVC Architecture',
        'oop' => 'OOP (Object Oriented Programming)',
        'agile' => 'Agile Methodology',
        'scrum' => 'Scrum',
        'kanban' => 'Kanban',
        'jira' => 'Jira',
        'testing' => 'Software Testing',
        'junit' => 'JUnit',
        'phpunit' => 'PHPUnit',
        'selenium' => 'Selenium',
        'microservices' => 'Microservices',
        'system design' => 'System Design',

        // Soft Skills & Professional Skills
        'communication' => 'Communication',
        'leadership' => 'Leadership',
        'management' => 'Project Management',
        'problem solving' => 'Problem Solving',
        'teamwork' => 'Team Collaboration',
        'collaboration' => 'Team Collaboration',
        'critical thinking' => 'Critical Thinking',
        'time management' => 'Time Management'
    ];

    // Section triggers
    private static $sectionsMap = [
        'education' => ['education', 'academic', 'degree', 'university', 'college', 'schooling'],
        'experience' => ['experience', 'employment', 'work history', 'professional background', 'work experience', 'career'],
        'skills' => ['skills', 'technical skills', 'core competencies', 'technologies', 'expertise', 'strengths'],
        'projects' => ['projects', 'academic projects', 'personal projects', 'key projects'],
        'certifications' => ['certifications', 'certificates', 'courses', 'credentials', 'licensing', 'awards'],
        'summary' => ['summary', 'profile', 'objective', 'about me', 'professional summary']
    ];

    /**
     * Analyzes resume text against job description text.
     * 
     * @param string $resumeText
     * @param string $jdText
     * @return array
     */
    public static function analyze(string $resumeText, string $jdText): array {
        // Preprocess texts - replace all types of spacing/newlines with single spaces for robust regex matching
        $resumeNormalized = preg_replace('/\s+/', ' ', $resumeText);
        $jdNormalized = preg_replace('/\s+/', ' ', $jdText);
        
        $resumeLower = strtolower($resumeNormalized);
        $jdLower = strtolower($jdNormalized);

        // 1. Contact Information Extraction
        $contactInfo = self::extractContactInfo($resumeNormalized);

        // 2. Sections Analysis
        $sections = self::analyzeSections($resumeLower);

        // 3. Skill & Keyword Matching
        $skillsAnalysis = self::matchSkills($resumeLower, $jdLower);

        // 4. Experience Parsing
        $experienceAnalysis = self::analyzeExperience($resumeNormalized, $jdNormalized);

        // 5. Formatting & Readability Checks
        $formattingAnalysis = self::checkFormatting($resumeText, $contactInfo);

        // 6. Score Calculation
        $scores = self::calculateScores($skillsAnalysis, $sections, $experienceAnalysis, $formattingAnalysis);

        return [
            'scores' => $scores,
            'contact_info' => $contactInfo,
            'sections' => $sections,
            'skills' => $skillsAnalysis,
            'experience' => $experienceAnalysis,
            'formatting' => $formattingAnalysis,
            'stats' => [
                'resume_word_count' => str_word_count($resumeText),
                'jd_word_count' => str_word_count($jdText),
            ]
        ];
    }

    private static function extractContactInfo(string $text): array {
        $email = 'Not Found';
        $phone = 'Not Found';
        $linkedin = 'Not Found';
        $github = 'Not Found';

        // Email regex - supports optional spaces around @ and . from PDF typesetting spacing
        if (preg_match('/[a-z0-9._%+-]+\s*@\s*[a-z0-9.-]+\s*\.\s*[a-z]{2,6}/i', $text, $matches)) {
            $email = str_replace(' ', '', $matches[0]);
        }

        // Phone regex (supports international formats)
        if (preg_match('/(\+?[0-9]{1,4}[-.\s]?)?\(?[0-9]{3}\)?[-.\s]?[0-9]{3}[-.\s]?[0-9]{4}/', $text, $matches)) {
            $phone = $matches[0];
        }

        // LinkedIn profile - supports spaces around slashes and dots
        if (preg_match('/linkedin\s*\.\s*com\s*\/in\s*\/[a-z0-9_-]+/i', $text, $matches)) {
            $linkedin = 'https://' . str_replace(' ', '', $matches[0]);
        }

        // GitHub profile - supports spaces around slashes and dots
        if (preg_match('/github\s*\.\s*com\s*\/[a-z0-9_-]+/i', $text, $matches)) {
            $github = 'https://' . str_replace(' ', '', $matches[0]);
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'linkedin' => $linkedin,
            'github' => $github
        ];
    }

    private static function analyzeSections(string $textLower): array {
        $sectionsStatus = [];
        foreach (self::$sectionsMap as $section => $keywords) {
            $found = false;
            foreach ($keywords as $kw) {
                // Look for heading patterns (e.g., word on its own line or followed by space/newlines)
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $textLower)) {
                    $found = true;
                    break;
                }
            }
            $sectionsStatus[$section] = $found;
        }
        return $sectionsStatus;
    }

    private static function matchSkills(string $resumeLower, string $jdLower): array {
        $resumeSkills = [];
        $jdSkills = [];

        // Match skills in resume and JD using boundary check
        foreach (self::$skillsDictionary as $keyword => $displayName) {
            // Escape special chars like C++ or .NET
            $escapedKeyword = preg_quote($keyword, '/');
            
            // Boundary matching logic. We handle cases like 'php' but avoid matching 'php' inside 'triumph'
            $pattern = '/\b' . $escapedKeyword . '\b/i';
            if ($keyword === 'c++') {
                $pattern = '/c\+\+/i';
            } elseif ($keyword === 'asp.net') {
                $pattern = '/asp\.net/i';
            } elseif ($keyword === 'next.js' || $keyword === 'node.js' || $keyword === 'vue.js' || $keyword === 'react.js' || $keyword === 'express.js') {
                $pattern = '/' . preg_quote($keyword, '/') . '/i';
            }

            if (preg_match($pattern, $resumeLower)) {
                $resumeSkills[$displayName] = true;
            }
            if (preg_match($pattern, $jdLower)) {
                $jdSkills[$displayName] = true;
            }
        }

        $resumeSkillsKeys = array_keys($resumeSkills);
        $jdSkillsKeys = array_keys($jdSkills);

        // If JD has no skills recognized, we'll try to extract some generic keywords or match everything
        $matched = array_values(array_intersect($resumeSkillsKeys, $jdSkillsKeys));
        $missing = array_values(array_diff($jdSkillsKeys, $resumeSkillsKeys));
        $extra = array_values(array_diff($resumeSkillsKeys, $jdSkillsKeys));

        return [
            'resume_skills' => $resumeSkillsKeys,
            'jd_skills' => $jdSkillsKeys,
            'matched' => $matched,
            'missing' => $missing,
            'extra' => $extra
        ];
    }

    private static function analyzeExperience(string $resumeText, string $jdText): array {
        // Estimate years of experience in resume
        $resumeYears = self::extractYears($resumeText);
        // Estimate years of experience required in Job Description
        $jdYears = self::extractYears($jdText);

        $status = 'Neutral';
        $message = 'Experience comparison could not be fully determined.';

        if ($jdYears > 0) {
            if ($resumeYears >= $jdYears) {
                $status = 'Met';
                $message = "Matches or exceeds the requirement of {$jdYears}+ years (Estimated resume experience: {$resumeYears} years).";
            } else {
                $status = 'Gap';
                $message = "Under the requirement of {$jdYears}+ years (Estimated resume experience: {$resumeYears} years).";
            }
        } else {
            if ($resumeYears > 0) {
                $message = "Estimated experience: {$resumeYears} years (No specific target found in job description).";
            } else {
                $message = "Experience timeline not clearly defined in resume.";
            }
        }

        return [
            'resume_years' => $resumeYears,
            'jd_years' => $jdYears,
            'status' => $status,
            'message' => $message
        ];
    }

    private static function extractYears(string $text): float {
        // Look for expressions like "5+ years", "3 years of experience", "6 years", etc.
        if (preg_match('/(\d+(?:\.\d+)?)\s*\+?\s*years?\s+(?:of\s+)?experience/i', $text, $matches)) {
            return (float)$matches[1];
        }
        if (preg_match('/experience\b.{0,15}\b(\d+(?:\.\d+)?)\s*\+?\s*years?/i', $text, $matches)) {
            return (float)$matches[1];
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*\+?\s*years?\b/i', $text, $matches)) {
            return (float)$matches[1];
        }

        // Alternative check: Parse dates like "2018 - 2022" or "2018 to Present" and aggregate duration
        // We can do a simple pattern matching for years
        preg_match_all('/\b(19\d{2}|20\d{2})\b\s*(?:-|to)\s*\b(19\d{2}|20\d{2}|present|current|now)\b/i', $text, $dateMatches);
        $totalYears = 0;
        if (!empty($dateMatches[0])) {
            foreach ($dateMatches[1] as $idx => $startYear) {
                $end = $dateMatches[2][$idx];
                $endYear = in_array(strtolower($end), ['present', 'current', 'now']) ? (int)date('Y') : (int)$end;
                $diff = $endYear - (int)$startYear;
                if ($diff > 0 && $diff < 40) { // sanity check
                    $totalYears += $diff;
                }
            }
        }

        return $totalYears > 0 ? (float)$totalYears : 0.0;
    }

    private static function checkFormatting(string $text, array $contact): array {
        $wordCount = str_word_count($text);
        
        // Check for bullets
        $hasBullets = preg_match('/[\x{2022}\x{2023}\x{25E6}\x{2043}\x{2219}•\-*]/u', $text) ? true : false;
        
        $issues = [];
        $suggestions = [];

        if ($contact['email'] === 'Not Found') {
            $issues[] = 'Missing email address';
            $suggestions[] = 'Add a professional email address in the header.';
        }
        if ($contact['phone'] === 'Not Found') {
            $issues[] = 'Missing phone number';
            $suggestions[] = 'Add your contact number so recruiters can reach out easily.';
        }
        if ($contact['linkedin'] === 'Not Found') {
            $suggestions[] = 'Consider adding your LinkedIn profile URL.';
        }

        if ($wordCount < 300) {
            $issues[] = 'Resume is too short';
            $suggestions[] = 'Expand your content with detailed projects, achievements, and quantified responsibilities.';
        } elseif ($wordCount > 1500) {
            $issues[] = 'Resume is very long';
            $suggestions[] = 'Trim and condense your resume. Ideally, keep it to 1-2 pages (under 1000 words).';
        }

        if (!$hasBullets) {
            $issues[] = 'No bullet points detected';
            $suggestions[] = 'Use bulleted lists rather than long paragraphs to list your duties and achievements.';
        }

        return [
            'issues' => $issues,
            'suggestions' => $suggestions,
            'has_bullets' => $hasBullets,
            'word_count' => $wordCount
        ];
    }

    private static function calculateScores(array $skills, array $sections, array $exp, array $format): array {
        // 1. Skill Match (50% weight)
        $skillsScore = 100;
        if (!empty($skills['jd_skills'])) {
            $skillsScore = (count($skills['matched']) / count($skills['jd_skills'])) * 100;
        }

        // 2. Sections presence (20% weight)
        // Check 4 main parts: summary, education, experience, skills (25% each)
        $sectionCount = 0;
        $sectionsToCheck = ['summary', 'education', 'experience', 'skills'];
        foreach ($sectionsToCheck as $s) {
            if (!empty($sections[$s])) {
                $sectionCount++;
            }
        }
        $sectionsScore = ($sectionCount / count($sectionsToCheck)) * 100;

        // 3. Formatting Score (15% weight)
        $formatScore = 100;
        if ($format['word_count'] < 200 || $format['word_count'] > 2000) $formatScore -= 30;
        if (!$format['has_bullets']) $formatScore -= 30;
        if (count($format['issues']) > 0) {
            $formatScore -= (count($format['issues']) * 15);
        }
        $formatScore = max(10, min(100, $formatScore));

        // 4. Experience Match Score (15% weight)
        $expScore = 100;
        if ($exp['status'] === 'Gap') {
            $diff = $exp['jd_years'] - $exp['resume_years'];
            $expScore = max(30, 100 - ($diff * 20));
        } elseif ($exp['status'] === 'Neutral' && $exp['resume_years'] == 0) {
            $expScore = 60; // neutral but no exp found
        }

        // Overall Weighted Score
        $overall = ($skillsScore * 0.50) + ($sectionsScore * 0.20) + ($formatScore * 0.15) + ($expScore * 0.15);

        return [
            'overall' => round($overall),
            'skills' => round($skillsScore),
            'sections' => round($sectionsScore),
            'formatting' => round($formatScore),
            'experience' => round($expScore)
        ];
    }
}
