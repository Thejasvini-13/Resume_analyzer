<?php
require_once 'parser.php';
require_once 'analyzer.php';

$analysisResult = null;
$error = null;
$jdInput = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jdInput = $_POST['job_description'] ?? '';
    
    try {
        if (empty($jdInput)) {
            throw new Exception("Please provide a Job Description to match against.");
        }

        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
            // Check specific file upload error
            $errorCode = $_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE;
            switch ($errorCode) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new Exception("The uploaded file exceeds the maximum allowed size.");
                case UPLOAD_ERR_NO_FILE:
                    throw new Exception("Please upload a resume file.");
                default:
                    throw new Exception("File upload failed. Please try again.");
            }
        }

        $fileTmpPath = $_FILES['resume']['tmp_name'];
        $fileName = $_FILES['resume']['name'];
        $fileSize = $_FILES['resume']['size'];
        $fileType = $_FILES['resume']['type'];
        
        // Extract text using ResumeParser
        $extractedText = ResumeParser::extractText($fileTmpPath, $fileName);
        
        if (empty(trim($extractedText))) {
            throw new Exception("Could not extract text from this file. Ensure it is not an image-only/scanned PDF.");
        }

        // Analyze resume text against job description
        $analysisResult = ResumeAnalyzer::analyze($extractedText, $jdInput);
        $analysisResult['raw_text'] = $extractedText; // Save for view tab
        $analysisResult['file_name'] = $fileName;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Powered Resume Analyzer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Resume ATS Analyzer</h1>
            <p>Optimize your resume against Job Descriptions, identify skill gaps, and bypass applicant tracking systems.</p>
        </header>

        <div class="analyzer-grid">
            <!-- Left Panel: Input & Form -->
            <div class="glass-panel" id="panel-form" style="<?php echo $analysisResult ? 'display: none;' : ''; ?>">
                <?php if ($error): ?>
                    <div class="checklist-item mb-6" style="background: var(--danger-bg); padding: 16px; border-radius: var(--radius-sm); border: 1px solid rgba(239, 68, 68, 0.2);">
                        <div class="checklist-icon danger">✕</div>
                        <div class="checklist-text">
                            <h4 style="color: var(--danger);">Error</h4>
                            <p style="color: #fca5a5;"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="index.php" method="POST" enctype="multipart/form-data" id="analyzer-form">
                    <div class="form-group">
                        <label class="form-label">Upload Resume</label>
                        <div class="upload-zone" id="upload-zone">
                            <div class="upload-prompt" id="upload-prompt-text">
                                <div class="upload-icon">↑</div>
                                <p>Drag and drop your file here or <strong>browse</strong></p>
                                <span>Supports PDF, DOCX, TXT (Max 5MB)</span>
                            </div>
                            <!-- Hidden File Input -->
                            <input type="file" name="resume" id="resume-file" class="file-input" accept=".pdf,.docx,.txt" required>
                            
                            <!-- File selected details -->
                            <div class="file-selected-details" id="file-details">
                                <div class="file-info">
                                    <span class="file-info-icon">📄</span>
                                    <span class="file-name" id="file-name">filename.pdf</span>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;" id="file-details-size"></span>
                                </div>
                                <button type="button" class="remove-file" id="remove-file">✕</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Job Description</label>
                        <textarea name="job_description" class="textarea-jd" placeholder="Paste the job description or requirement details here..." required><?php echo htmlspecialchars($jdInput); ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        Analyze Match Score ⚡
                    </button>
                </form>
            </div>

            <!-- Left Panel: Loading State -->
            <div class="glass-panel loading-overlay" id="loading-overlay">
                <div class="spinner"></div>
                <h3 style="margin-bottom: 8px;">Processing Resume...</h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Extracting content, matching skills, and conducting ATS scoring metrics...</p>
            </div>

            <!-- Right Panel / Second Column: Results Presentation -->
            <div class="glass-panel" id="panel-results" style="<?php echo !$analysisResult ? 'display: none;' : ''; ?>">
                <?php if ($analysisResult): ?>
                    <div class="results-header">
                        <h2>ATS Match Analysis</h2>
                        <a href="index.php" class="badge-status neutral" style="text-decoration: none; cursor: pointer; padding: 8px 16px;">← Start Over</a>
                    </div>

                    <!-- Circular Chart & Score Summary -->
                    <div class="score-hero-container">
                        <div class="score-chart-wrapper">
                            <svg viewBox="0 0 36 36" class="circular-chart primary">
                                <defs>
                                    <linearGradient id="gradient-primary" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#8b5cf6" />
                                        <stop offset="100%" stop-color="#ec4899" />
                                    </linearGradient>
                                </defs>
                                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path class="circle" stroke-dasharray="<?php echo $analysisResult['scores']['overall']; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <text x="18" y="20.35" class="percentage"><?php echo $analysisResult['scores']['overall']; ?>%</text>
                            </svg>
                        </div>
                        <div class="score-details">
                            <h3>Overall Score</h3>
                            <p>For file: <strong><?php echo htmlspecialchars($analysisResult['file_name']); ?></strong></p>
                            <p class="mt-4" style="font-size: 0.9rem;">
                                <?php 
                                    $score = $analysisResult['scores']['overall'];
                                    if ($score >= 80) {
                                        echo "<span class='text-success'>★ Excellent Match.</span> This resume is highly aligned with the job description.";
                                    } elseif ($score >= 60) {
                                        echo "<span class='text-warning'>✦ Good Match.</span> A few minor updates can boost your chances significantly.";
                                    } else {
                                        echo "<span class='text-danger'>⚠ Low Match.</span> Review key missing skills and formatting issues highlighted below.";
                                    }
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Sub Scores Breakdown -->
                    <div class="sub-scores-grid">
                        <div class="sub-score-card">
                            <div class="sub-score-header">
                                <span class="sub-score-title">Skills Alignment</span>
                                <span class="sub-score-value"><?php echo $analysisResult['scores']['skills']; ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $analysisResult['scores']['skills']; ?>%;"></div>
                            </div>
                        </div>

                        <div class="sub-score-card">
                            <div class="sub-score-header">
                                <span class="sub-score-title">ATS Formatting</span>
                                <span class="sub-score-value"><?php echo $analysisResult['scores']['formatting']; ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $analysisResult['scores']['formatting']; ?>%; background: var(--secondary);"></div>
                            </div>
                        </div>

                        <div class="sub-score-card">
                            <div class="sub-score-header">
                                <span class="sub-score-title">Experience Check</span>
                                <span class="sub-score-value"><?php echo $analysisResult['scores']['experience']; ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $analysisResult['scores']['experience']; ?>%; background: var(--accent-blue);"></div>
                            </div>
                        </div>

                        <div class="sub-score-card">
                            <div class="sub-score-header">
                                <span class="sub-score-title">Section Presence</span>
                                <span class="sub-score-value"><?php echo $analysisResult['scores']['sections']; ?>%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $analysisResult['scores']['sections']; ?>%; background: var(--success);"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="tabs-navigation">
                        <button class="tab-btn active" data-tab="skills">Skill Gap</button>
                        <button class="tab-btn" data-tab="format">Audit & Format</button>
                        <button class="tab-btn" data-tab="profile">Experience & Contact</button>
                        <button class="tab-btn" data-tab="parsed">Parsed Resume</button>
                    </div>

                    <!-- Tab: Skill Gap -->
                    <div class="tab-content active" id="tab-skills">
                        <h3 class="mb-4">Keyword & Skill Evaluation</h3>
                        
                        <!-- Matched Skills -->
                        <div class="skills-group-title text-success">✓ Matched Skills (<?php echo count($analysisResult['skills']['matched']); ?>)</div>
                        <div class="skills-pill-box">
                            <?php if (empty($analysisResult['skills']['matched'])): ?>
                                <span style="color: var(--text-muted); font-size: 0.9rem;">No matching keywords found.</span>
                            <?php else: ?>
                                <?php foreach ($analysisResult['skills']['matched'] as $skill): ?>
                                    <span class="skill-pill matched">✓ <?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Missing Skills -->
                        <div class="skills-group-title text-danger">✗ Missing Core Skills (<?php echo count($analysisResult['skills']['missing']); ?>)</div>
                        <div class="skills-pill-box">
                            <?php if (empty($analysisResult['skills']['missing'])): ?>
                                <span class="text-success" style="font-size: 0.9rem;">Fantastic! No missing skills from the job description.</span>
                            <?php else: ?>
                                <?php foreach ($analysisResult['skills']['missing'] as $skill): ?>
                                    <span class="skill-pill missing">✗ <?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Extra Skills -->
                        <div class="skills-group-title" style="color: var(--primary-light);">✦ Additional Skills (<?php echo count($analysisResult['skills']['extra']); ?>)</div>
                        <div class="skills-pill-box">
                            <?php if (empty($analysisResult['skills']['extra'])): ?>
                                <span style="color: var(--text-muted); font-size: 0.9rem;">No additional skills recognized.</span>
                            <?php else: ?>
                                <?php foreach ($analysisResult['skills']['extra'] as $skill): ?>
                                    <span class="skill-pill extra">✦ <?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab: Audit & Format -->
                    <div class="tab-content" id="tab-format">
                        <h3 class="mb-4">ATS Compliance Audit</h3>

                        <div class="mb-6">
                            <h4 class="mb-4">Section Checklist</h4>
                            <div class="section-comp-grid">
                                <?php foreach ($analysisResult['sections'] as $sectionName => $isPresent): ?>
                                    <div class="section-comp-item">
                                        <span class="section-comp-name"><?php echo htmlspecialchars($sectionName); ?></span>
                                        <span class="section-comp-status <?php echo $isPresent ? 'found' : 'missing'; ?>">
                                            <?php echo $isPresent ? '✓ Found' : '✗ Missing'; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <h4 class="mb-4">Formatting Suggestions</h4>
                            <?php if (empty($analysisResult['formatting']['suggestions']) && empty($analysisResult['formatting']['issues'])): ?>
                                <p class="text-success">Formatting and structure adhere to ATS guidelines perfectly!</p>
                            <?php else: ?>
                                <?php foreach ($analysisResult['formatting']['issues'] as $issue): ?>
                                    <div class="checklist-item">
                                        <div class="checklist-icon danger">✗</div>
                                        <div class="checklist-text">
                                            <h4><?php echo htmlspecialchars($issue); ?></h4>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php foreach ($analysisResult['formatting']['suggestions'] as $suggestion): ?>
                                    <div class="checklist-item">
                                        <div class="checklist-icon warning">⚠</div>
                                        <div class="checklist-text">
                                            <p><?php echo htmlspecialchars($suggestion); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tab: Experience & Contact -->
                    <div class="tab-content" id="tab-profile">
                        <h3 class="mb-4">Metadata & Job Match Criteria</h3>
                        
                        <div class="mb-6">
                            <h4 class="mb-4">Experience Comparison</h4>
                            <div class="info-row">
                                <span class="info-row-label">Estimated Resume Experience</span>
                                <span class="info-row-value"><?php echo $analysisResult['experience']['resume_years']; ?> Years</span>
                            </div>
                            <div class="info-row">
                                <span class="info-row-label">JD Required Experience</span>
                                <span class="info-row-value"><?php echo $analysisResult['experience']['jd_years'] > 0 ? $analysisResult['experience']['jd_years'] . ' Years' : 'Not Specified'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-row-label">Status</span>
                                <span class="info-row-value">
                                    <span class="badge-status <?php echo strtolower($analysisResult['experience']['status']); ?>">
                                        <?php echo htmlspecialchars($analysisResult['experience']['status']); ?>
                                    </span>
                                </span>
                            </div>
                            <p class="mt-4" style="font-size: 0.9rem; color: var(--text-secondary);"><?php echo htmlspecialchars($analysisResult['experience']['message']); ?></p>
                        </div>

                        <div>
                            <h4 class="mb-4">Extracted Contact Information</h4>
                            <div class="info-row">
                                <span class="info-row-label">Email Address</span>
                                <span class="info-row-value"><?php echo htmlspecialchars($analysisResult['contact_info']['email']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-row-label">Phone Number</span>
                                <span class="info-row-value"><?php echo htmlspecialchars($analysisResult['contact_info']['phone']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-row-label">LinkedIn</span>
                                <span class="info-row-value">
                                    <?php if ($analysisResult['contact_info']['linkedin'] !== 'Not Found'): ?>
                                        <a href="<?php echo htmlspecialchars($analysisResult['contact_info']['linkedin']); ?>" target="_blank" style="color: var(--primary-light); text-decoration: none;">View Profile ↗</a>
                                    <?php else: ?>
                                        Not Found
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-row-label">GitHub</span>
                                <span class="info-row-value">
                                    <?php if ($analysisResult['contact_info']['github'] !== 'Not Found'): ?>
                                        <a href="<?php echo htmlspecialchars($analysisResult['contact_info']['github']); ?>" target="_blank" style="color: var(--primary-light); text-decoration: none;">View Profile ↗</a>
                                    <?php else: ?>
                                        Not Found
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Parsed Resume -->
                    <div class="tab-content" id="tab-parsed">
                        <h3 class="mb-4">Extracted Raw Text</h3>
                        <p class="mb-4" style="font-size: 0.85rem; color: var(--text-secondary);">Here is the raw text extracted by our PHP parsers. Use this to verify that special characters and layouts are parsed correctly.</p>
                        <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05); padding: 16px; border-radius: var(--radius-sm); max-height: 350px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                            <?php echo htmlspecialchars($analysisResult['raw_text']); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>
