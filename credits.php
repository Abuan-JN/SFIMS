<?php
/**
 * SFIMS Credits Page - High Fidelity & Performance Version
 * 
 * Displays team member information, tech stack, timeline, 
 * and acknowledgments with premium glassmorphism design.
 */

require_once 'config/database.php';
require_once 'config/app.php';

$page_title = 'Credits';
require_once 'partials/header.php';
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.05);
        --glass-border: rgba(255, 255, 255, 0.1);
        --accent-green: #4ade80;
        --deep-green: #1e451e;
        --text-bright: #ffffff;
    }

    [data-theme="light"] {
        --glass-bg: rgba(255, 255, 255, 0.8);
        --glass-border: rgba(0, 0, 0, 0.05);
        --text-bright: var(--sfims-text);
    }

    /* Hero Section with Moving Pattern */
    .credits-hero {
        background: linear-gradient(135deg, var(--sfims-green) 0%, #2d5a27 100%);
        padding: 50px 20px;
        text-align: center;
        margin-bottom: 40px;
        border-radius: 20px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    }

    .credits-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(74, 222, 128, 0.15) 0%, transparent 40%),
            radial-gradient(circle at 80% 70%, rgba(74, 222, 128, 0.1) 0%, transparent 40%);
        pointer-events: none;
    }

    .credits-hero h1 {
        font-size: 2.8rem;
        font-weight: 800;
        color: #fff;
        margin-bottom: 10px;
        text-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }

    .credits-hero p {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.9);
        max-width: 650px;
        margin: 0 auto;
    }

    /* Stats Section */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .stat-box {
        background: var(--sfims-card-bg);
        border: 1px solid var(--sfims-border);
        padding: 25px 15px;
        border-radius: 16px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-box:hover {
        transform: translateY(-8px);
        border-color: var(--accent-green);
        box-shadow: 0 10px 25px rgba(74, 222, 128, 0.15);
    }

    .stat-val {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--accent-green);
        margin-bottom: 5px;
        line-height: 1;
    }

    .stat-lbl {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.7;
    }

    /* Tabs Styling */
    .nav-pills-custom {
        margin-bottom: 40px;
        background: var(--sfims-card-bg);
        padding: 8px;
        border-radius: 40px;
        border: 1px solid var(--sfims-border);
        display: inline-flex;
        width: auto;
    }

    .nav-pills-custom .nav-link {
        border-radius: 30px;
        padding: 10px 25px;
        font-weight: 600;
        color: var(--sfims-text);
        transition: all 0.3s ease;
        border: none;
    }

    .nav-pills-custom .nav-link.active {
        background: var(--sfims-green);
        color: #fff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    /* Team Cards - Glassmorphism */
    .team-card {
        background: var(--sfims-card-bg);
        border: 1px solid var(--sfims-border);
        border-radius: 20px;
        padding: 30px;
        text-align: center;
        height: 100%;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        cursor: pointer;
        position: relative;
    }

    .team-card:hover {
        transform: translateY(-12px) scale(1.02);
        border-color: var(--accent-green);
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }

    .member-img-wrap {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        margin: 0 auto 20px;
        border: 4px solid var(--sfims-border);
        padding: 3px;
        transition: all 0.4s ease;
    }

    .team-card:hover .member-img-wrap {
        border-color: var(--accent-green);
        transform: rotate(5deg);
    }

    .member-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }

    .member-role {
        color: var(--accent-green);
        font-size: 0.9rem;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 10px;
    }

    .member-bio {
        font-size: 0.9rem;
        opacity: 0.8;
        line-height: 1.6;
    }

    /* Tech Stack Items */
    .tech-pill {
        background: var(--sfims-bg);
        border: 1px solid var(--sfims-border);
        padding: 12px 20px;
        border-radius: 12px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
    }

    .tech-pill:hover {
        background: rgba(74, 222, 128, 0.1);
        border-color: var(--accent-green);
        transform: translateX(5px);
    }

    .tech-pill i {
        font-size: 1.4rem;
        color: var(--accent-green);
    }

    /* Timeline Styling */
    .timeline {
        position: relative;
        padding: 20px 0;
    }

    .timeline::before {
        content: '';
        position: absolute;
        width: 2px;
        background: var(--sfims-border);
        top: 0; bottom: 0; left: 50%;
        margin-left: -1px;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 40px;
        width: 50%;
        padding: 0 30px;
    }

    .timeline-item:nth-child(odd) { left: 0; text-align: right; }
    .timeline-item:nth-child(even) { left: 50%; text-align: left; }

    .timeline-dot {
        width: 16px; height: 16px;
        background: var(--accent-green);
        border: 3px solid var(--sfims-card-bg);
        border-radius: 50%;
        position: absolute;
        top: 5px;
        left: 50%;
        margin-left: -8px;
        z-index: 1;
    }

    .timeline-content {
        background: var(--sfims-card-bg);
        padding: 15px 20px;
        border-radius: 12px;
        border: 1px solid var(--sfims-border);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    /* Modal Tweaks */
    .modal-content-glass {
        background: var(--sfims-card-bg);
        border: 1px solid var(--glass-border);
        backdrop-filter: blur(15px);
        border-radius: 24px;
    }

    .skill-badge {
        background: rgba(74, 222, 128, 0.1);
        color: var(--accent-green);
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        margin: 3px;
        display: inline-block;
        border: 1px solid rgba(74, 222, 128, 0.2);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .timeline::before { left: 20px; }
        .timeline-item { width: 100%; padding-left: 50px; text-align: left !important; }
        .timeline-dot { left: 20px; }
        .nav-pills-custom { width: 100%; overflow-x: auto; }
    }
</style>

<div class="container py-4">
    <?php if (!is_logged_in()): ?>
    <div class="mb-4">
        <a href="auth/login.php" class="btn btn-outline-success rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i> Back to Login
        </a>
    </div>
    <?php endif; ?>
    <!-- Hero Section -->
    <div class="credits-hero">
        <h1>Meet Our Visionaries</h1>
        <p>The innovative team behind SFIMS, dedicated to excellence in supply and facilities management at PLMun.</p>
        <div class="mt-4">
            <span class="badge bg-light text-dark px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-rocket-takeoff me-2"></i> Version 2.5
            </span>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-container">
        <div class="stat-box">
            <div class="stat-val" data-target="3">0</div>
            <div class="stat-lbl">Core Members</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" data-target="18">0</div>
            <div class="stat-lbl">Active Modules</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" data-target="7">0</div>
            <div class="stat-lbl">Months Dev</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" data-target="100">0</div>
            <div class="stat-lbl">% Dedication</div>
        </div>
    </div>

    <!-- Tabbed Content Section -->
    <div class="text-center">
        <div class="nav nav-pills nav-pills-custom" id="creditsTabs" role="tablist">
            <button class="nav-link active" id="team-tab" data-bs-toggle="pill" data-bs-target="#team" type="button" role="tab">
                <i class="bi bi-people me-2"></i> Team
            </button>
            <button class="nav-link" id="tech-tab" data-bs-toggle="pill" data-bs-target="#tech" type="button" role="tab">
                <i class="bi bi-code-slash me-2"></i> Tech Stack
            </button>
            <button class="nav-link" id="journey-tab" data-bs-toggle="pill" data-bs-target="#journey" type="button" role="tab">
                <i class="bi bi-clock-history me-2"></i> Journey
            </button>
            <button class="nav-link" id="ack-tab" data-bs-toggle="pill" data-bs-target="#ack" type="button" role="tab">
                <i class="bi bi-heart me-2"></i> Thanks
            </button>
        </div>
    </div>

    <div class="tab-content mt-2" id="creditsTabsContent">
        <!-- Team Tab -->
        <div class="tab-pane fade show active" id="team" role="tabpanel">
            <div class="row g-4">
                <!-- Member 1 -->
                <div class="col-md-4">
                    <div class="team-card" onclick="openMemberModal('jaifi')">
                        <div class="member-img-wrap">
                            <img src="<?php echo BASE_URL; ?>assets/img/team/jnplmun.jpg" onerror="this.src='https://ui-avatars.com/api/?name=Jaifi+Niel&background=1e451e&color=fff&size=150'">
                        </div>
                        <h3>Jaifi Niel D. Abuan</h3>
                        <p class="member-role">Project Manager & Lead Dev</p>
                        <p class="member-bio">Leading the architectural vision and backend integrity of SFIMS.</p>
                        <div class="mt-3">
                            <span class="skill-badge">PHP</span>
                            <span class="skill-badge">MySQL</span>
                            <span class="skill-badge">Architecture</span>
                        </div>
                    </div>
                </div>
                <!-- Member 2 -->
                <div class="col-md-4">
                    <div class="team-card" onclick="openMemberModal('ben')">
                        <div class="member-img-wrap">
                            <img src="<?php echo BASE_URL; ?>assets/img/team/aradoplmun.jpg" onerror="this.src='https://ui-avatars.com/api/?name=Benjamin+Arado&background=1e451e&color=fff&size=150'">
                        </div>
                        <h3>Benjamin Arado</h3>
                        <p class="member-role">Frontend Specialist</p>
                        <p class="member-bio">Crafting intuitive and responsive user experiences for every screen.</p>
                        <div class="mt-3">
                            <span class="skill-badge">UI/UX</span>
                            <span class="skill-badge">JS</span>
                            <span class="skill-badge">Bootstrap</span>
                        </div>
                    </div>
                </div>
                <!-- Member 3 -->
                <div class="col-md-4">
                    <div class="team-card" onclick="openMemberModal('ally')">
                        <div class="member-img-wrap">
                            <img src="<?php echo BASE_URL; ?>assets/img/team/lopenaplmun.jpg" onerror="this.src='https://ui-avatars.com/api/?name=Allysa+Mae&background=1e451e&color=fff&size=150'">
                        </div>
                        <h3>Allysa Mae Lopena</h3>
                        <p class="member-role">Documentation & QA</p>
                        <p class="member-bio">Ensuring system robustness through rigorous testing and documentation.</p>
                        <div class="mt-3">
                            <span class="skill-badge">QA Testing</span>
                            <span class="skill-badge">Docs</span>
                            <span class="skill-badge">Support</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tech Tab -->
        <div class="tab-pane fade" id="tech" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="tech-pill"><i class="bi bi-filetype-php"></i> <div><strong>PHP 8.2</strong> - Core logic and backend engine</div></div>
                    <div class="tech-pill"><i class="bi bi-database"></i> <div><strong>MySQL</strong> - Optimized relational data storage</div></div>
                    <div class="tech-pill"><i class="bi bi-bootstrap"></i> <div><strong>Bootstrap 5</strong> - Modern responsive grid system</div></div>
                </div>
                <div class="col-md-6">
                    <div class="tech-pill"><i class="bi bi-code-square"></i> <div><strong>JavaScript ES6</strong> - Dynamic client-side interactions</div></div>
                    <div class="tech-pill"><i class="bi bi-shield-lock"></i> <div><strong>Bcrypt/PDO</strong> - State-of-the-art security practices</div></div>
                    <div class="tech-pill"><i class="bi bi-google"></i> <div><strong>Google Fonts</strong> - Clean, modern typography</div></div>
                </div>
            </div>
        </div>

        <!-- Journey Tab -->
        <div class="tab-pane fade" id="journey" role="tabpanel">
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <strong>Aug 2025</strong>
                        <h6>Inception</h6>
                        <p class="small mb-0">Initial requirements gathering at PLMun SPMO.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <strong>Oct 2025</strong>
                        <h6>Core Engine</h6>
                        <p class="small mb-0">Database architecture and item masterlist completion.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <strong>Jan 2026</strong>
                        <h6>Advanced Ops</h6>
                        <p class="small mb-0">Disbursement and inventory tracking launch.</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <strong>Current</strong>
                        <h6>Deployment</h6>
                        <p class="small mb-0">Production ready and ongoing live maintenance.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thanks Tab -->
        <div class="tab-pane fade" id="ack" role="tabpanel">
            <div class="p-4 bg-light rounded shadow-sm border">
                <h5 class="mb-4"><i class="bi bi-stars text-warning me-2"></i>Special Acknowledgments</h5>
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent d-flex align-items-center">
                        <i class="bi bi-check2-circle text-success me-3 fs-5"></i>
                        <span><strong>PLMun Administration</strong> - For the trust and resources provided.</span>
                    </li>
                    <li class="list-group-item bg-transparent d-flex align-items-center">
                        <i class="bi bi-check2-circle text-success me-3 fs-5"></i>
                        <span><strong>IT Faculty</strong> - For mentorship and technical guidance.</span>
                    </li>
                    <li class="list-group-item bg-transparent d-flex align-items-center">
                        <i class="bi bi-check2-circle text-success me-3 fs-5"></i>
                        <span><strong>SPMO Staff</strong> - For real-world workflow insights.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-5 mb-3 opacity-75">
        <small>© <?php echo date('Y'); ?> SFIMS PLMun Dev Team | All Rights Reserved</small>
    </div>
</div>

<!-- Member Details Modal -->
<div class="modal fade" id="memberModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-glass">
            <div class="modal-body p-4 text-center">
                <button type="button" class="btn-close float-end" data-bs-dismiss="modal"></button>
                <div class="member-img-wrap mt-4">
                    <img id="m-img" src="" alt="Member">
                </div>
                <h3 id="m-name" class="mt-3 fw-800"></h3>
                <p id="m-role" class="member-role mb-3"></p>
                <p id="m-bio" class="px-3"></p>
                <div id="m-skills" class="mb-4"></div>
                <div class="d-flex justify-content-center gap-3">
                    <a href="#" class="btn btn-outline-success rounded-circle p-2"><i class="bi bi-linkedin"></i></a>
                    <a href="#" class="btn btn-outline-success rounded-circle p-2"><i class="bi bi-github"></i></a>
                    <a href="#" class="btn btn-outline-success rounded-circle p-2"><i class="bi bi-envelope"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Stats Counter Animation
    const stats = document.querySelectorAll('.stat-val');
    stats.forEach(stat => {
        const target = +stat.getAttribute('data-target');
        let count = 0;
        const speed = target > 50 ? 20 : 100;
        const inc = target / (2000 / speed);
        
        const update = () => {
            count += inc;
            if (count < target) {
                stat.innerText = Math.ceil(count);
                setTimeout(update, speed);
            } else {
                stat.innerText = target;
            }
        };
        update();
    });

    const memberData = {
        'jaifi': {
            name: 'Jaifi Niel D. Abuan',
            role: 'Project Manager & Lead Developer',
            bio: 'Expert in system architecture and backend engineering, ensuring high availability and security for the entire SFIMS platform.',
            skills: ['PHP', 'MySQL', 'System Design', 'Team Leadership'],
            img: '<?php echo BASE_URL; ?>assets/img/team/jnplmun.jpg'
        },
        'ben': {
            name: 'Benjamin Arado',
            role: 'Frontend Developer & UI/UX',
            bio: 'Passionate about creating clean, modern, and accessible user interfaces that make management tasks feel effortless.',
            skills: ['Bootstrap', 'JavaScript', 'UI Design', 'Responsive CSS'],
            img: '<?php echo BASE_URL; ?>assets/img/team/aradoplmun.jpg'
        },
        'ally': {
            name: 'Allysa Mae Lopena',
            role: 'QA Specialist & Technical Writer',
            bio: 'Focused on system reliability and clear user communication through detailed testing phases and comprehensive manual creation.',
            skills: ['QA Testing', 'Technical Writing', 'User Support', 'Data Analysis'],
            img: '<?php echo BASE_URL; ?>assets/img/team/lopenaplmun.jpg'
        }
    };

    function openMemberModal(id) {
        const data = memberData[id];
        document.getElementById('m-name').innerText = data.name;
        document.getElementById('m-role').innerText = data.role;
        document.getElementById('m-bio').innerText = data.bio;
        document.getElementById('m-img').src = data.img;
        
        const skillsDiv = document.getElementById('m-skills');
        skillsDiv.innerHTML = '';
        data.skills.forEach(s => {
            const span = document.createElement('span');
            span.className = 'skill-badge';
            span.innerText = s;
            skillsDiv.appendChild(span);
        });

        new bootstrap.Modal(document.getElementById('memberModal')).show();
    }
</script>

<?php require_once 'partials/footer.php'; ?>
