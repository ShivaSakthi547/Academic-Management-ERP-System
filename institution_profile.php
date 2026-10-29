<?php
// institution_profile.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';

// Fetch institution profile
try {
    $stmt = $pdo->query("SELECT * FROM institution_profile LIMIT 1");
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) {
        // Fallback if table is empty
        $profile = [
            'school_name' => 'Smart Campus Matric Higher Secondary School',
            'institution_code' => 'SCMHSS-2000',
            'board' => 'State Board of Tamil Nadu',
            'email' => 'smartcampus@gmail.com',
            'phone' => '8300844322',
            'address' => "Smart Campus Matric Higher Secondary School\nNo. 45, Knowledge Avenue,\nEducational Nagar,\nKarur,\nTamil Nadu - 639001,\nIndia",
            'principal' => 'Lakshmi',
            'website' => 'greencampus.in',
            'established_year' => '2000',
            'total_students' => 5000,
            'total_staff' => 125,
            'logo_path' => 'uploads/institution_logo.png'
        ];
    }
} catch (PDOException $e) {
    die("Database fetch failed: " . $e->getMessage());
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institution Profile - Academic Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .profile-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            border-radius: 16px;
            padding: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        .profile-logo {
            width: 140px;
            height: 140px;
            object-fit: contain;
            border-radius: 50%;
            background: white;
            padding: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border: 4px solid rgba(255, 255, 255, 0.2);
        }
        .info-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            height: 100%;
            background: #fff;
        }
        .info-card-header {
            background: transparent;
            border-bottom: 1px solid #f1f5f9;
            padding: 20px 24px;
            font-weight: 700;
            font-size: 1.1rem;
            color: #1e293b;
        }
        .info-card-body {
            padding: 24px;
        }
        .detail-row {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        .detail-icon {
            color: #3b82f6;
            font-size: 1.2rem;
            margin-right: 15px;
            width: 24px;
            text-align: center;
        }
        .detail-content {
            flex: 1;
        }
        .detail-label {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 1.05rem;
            color: #0f172a;
            font-weight: 500;
        }
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        .edit-btn {
            position: absolute;
            top: 20px;
            right: 20px;
        }
    </style>

    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <?php include 'sidebar.php'; ?>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <?php include 'topbar.php'; ?>

            

            <!-- Profile Header -->
            <div class="profile-header d-flex flex-column flex-md-row align-items-center text-center text-md-start">
                <?php if ($userRole === 'Admin'): ?>
                    <button class="btn btn-outline-light btn-sm edit-btn" data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="bi bi-pencil-square me-2"></i>Edit Profile
                    </button>
                <?php endif; ?>

                <div class="me-md-5 mb-4 mb-md-0">
                    <img src="<?php echo htmlspecialchars($profile['logo_path']); ?>" alt="Institution Logo" class="profile-logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="profile-logo align-items-center justify-content-center text-primary fw-bold fs-1" style="display:none; background:#fff; margin:0 auto;">SC</div>
                </div>
                <div>
                    <h1 class="fw-bold mb-2"><?php echo htmlspecialchars($profile['school_name']); ?></h1>
                    <p class="fs-5 text-light opacity-75 mb-3"><i class="bi bi-building me-2"></i><?php echo htmlspecialchars($profile['board']); ?></p>
                    <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-3">
                        <span class="badge bg-primary bg-opacity-25 border border-primary px-3 py-2 fs-6 rounded-pill"><i class="bi bi-hash me-1"></i><?php echo htmlspecialchars($profile['institution_code']); ?></span>
                        <span class="badge bg-success bg-opacity-25 border border-success px-3 py-2 fs-6 rounded-pill"><i class="bi bi-calendar-event me-1"></i>Est. <?php echo htmlspecialchars($profile['established_year']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon text-primary"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-value"><?php echo htmlspecialchars($profile['total_students']); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon text-success"><i class="bi bi-person-badge-fill"></i></div>
                        <div class="stat-value"><?php echo htmlspecialchars($profile['total_staff']); ?></div>
                        <div class="stat-label">Total Staff</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon text-warning"><i class="bi bi-calendar-check-fill"></i></div>
                        <div class="stat-value"><?php echo htmlspecialchars($profile['established_year']); ?></div>
                        <div class="stat-label">Established</div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon text-info"><i class="bi bi-award-fill"></i></div>
                        <div class="stat-value fs-4 mt-2 mb-3"><?php echo htmlspecialchars(explode(' ', $profile['board'])[0]); ?> Board</div>
                        <div class="stat-label">Affiliation</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Contact & Address -->
                <div class="col-md-6">
                    <div class="card info-card">
                        <div class="card-header info-card-header">
                            <i class="bi bi-envelope-paper me-2 text-primary"></i>Contact Information
                        </div>
                        <div class="card-body info-card-body">
                            <div class="detail-row">
                                <div class="detail-icon"><i class="bi bi-envelope"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Email Address</div>
                                    <div class="detail-value"><a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($profile['email']); ?></a></div>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="bi bi-telephone"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Phone Number</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($profile['phone']); ?></div>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="bi bi-globe"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Website</div>
                                    <div class="detail-value"><a href="https://<?php echo htmlspecialchars($profile['website']); ?>" target="_blank" class="text-decoration-none"><?php echo htmlspecialchars($profile['website']); ?></a></div>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="bi bi-person-video3"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Principal</div>
                                    <div class="detail-value fw-bold"><?php echo htmlspecialchars($profile['principal']); ?></div>
                                </div>
                            </div>
                            <hr class="my-4 text-muted">
                            <div class="detail-row mb-0">
                                <div class="detail-icon"><i class="bi bi-geo-alt"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Professional Address</div>
                                    <div class="detail-value lh-base"><?php echo nl2br(htmlspecialchars($profile['address'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Details -->
                <div class="col-md-6">
                    <div class="card info-card">
                        <div class="card-header info-card-header">
                            <i class="bi bi-info-square me-2 text-success"></i>Additional Details
                        </div>
                        <div class="card-body info-card-body">
                            <div class="detail-row">
                                <div class="detail-icon"><i class="bi bi-gender-ambiguous"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">School Type</div>
                                    <div class="detail-value">Co-Educational</div>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="bi bi-translate"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Medium of Instruction</div>
                                    <div class="detail-value">English</div>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="bi bi-buildings"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Campus Type</div>
                                    <div class="detail-value">Urban</div>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-icon"><i class="bi bi-calendar-week"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Working Days</div>
                                    <div class="detail-value">Monday - Saturday</div>
                                </div>
                            </div>
                            <div class="detail-row mb-0">
                                <div class="detail-icon"><i class="bi bi-journal-bookmark"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Academic Year</div>
                                    <div class="detail-value">2026 - 2027</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php if ($userRole === 'Admin'): ?>
<!-- Edit Profile Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="editModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Institution Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="action.php?action=update_institution" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">School Name</label>
                            <input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($profile['school_name']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Institution Code</label>
                            <input type="text" name="institution_code" class="form-control" value="<?php echo htmlspecialchars($profile['institution_code']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Principal Name</label>
                            <input type="text" name="principal" class="form-control" value="<?php echo htmlspecialchars($profile['principal']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="text" name="website" class="form-control" value="<?php echo htmlspecialchars($profile['website']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Total Students</label>
                            <input type="number" name="total_students" class="form-control" value="<?php echo htmlspecialchars($profile['total_students']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Total Staff</label>
                            <input type="number" name="total_staff" class="form-control" value="<?php echo htmlspecialchars($profile['total_staff']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Professional Address</label>
                            <textarea name="address" class="form-control" rows="4" required><?php echo htmlspecialchars($profile['address']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
