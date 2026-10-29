<?php
// student_profile.php

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    echo "<div class='alert alert-danger'>Access Denied.</div>";
    return;
}

$student_username = $_SESSION['user_name']; // username is student_id

try {
    // Fetch full student profile with section
    $stmt_stu = $pdo->prepare("
        SELECT s.*, c.section, s.deleted_at
        FROM students s
        LEFT JOIN class_students cs ON s.id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.id
        WHERE s.student_id = ?
    ");
    $stmt_stu->execute([$student_username]);
    $stu_profile = $stmt_stu->fetch(PDO::FETCH_ASSOC);

    if (!$stu_profile) {
        echo "<div class='alert alert-danger'>Student profile not found.</div>";
        return;
    }

    $isActive = is_null($stu_profile['deleted_at']);
    $photo = !empty($stu_profile['photo']) ? $stu_profile['photo'] : 'uploads/default_avatar.png';

    // Fetch Attendance Summary
    $stmt_att = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
        FROM attendance 
        WHERE student_id = ?
    ");
    $stmt_att->execute([$stu_profile['id']]);
    $attendance_summary = $stmt_att->fetch(PDO::FETCH_ASSOC);
    
    $total_days = $attendance_summary['total_days'] ?? 0;
    $present_days = $attendance_summary['present_days'] ?? 0;
    $absent_days = $attendance_summary['absent_days'] ?? 0;
    $attendance_percent = $total_days > 0 ? round(($present_days / $total_days) * 100, 2) : 0;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card profile-card" style="border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); overflow: hidden; background: #fff;">
            <div class="profile-header" style="background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); padding: 40px 20px 20px; text-align: center; border-bottom: 1px solid #e9ecef;">
                <div class="profile-img-container mb-3" style="width: 180px; height: 180px; margin: 0 auto; border-radius: 50%; padding: 5px; background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); box-shadow: 0 8px 20px rgba(13, 110, 253, 0.2);">
                    <img src="<?php echo htmlspecialchars($photo); ?>" alt="Profile Photo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 4px solid #fff;" onerror="this.src='https://github.com/twbs.png'">
                </div>
                <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($stu_profile['student_name']); ?></h3>
                <p class="text-secondary mb-2">Student ID: <?php echo htmlspecialchars($stu_profile['student_id']); ?></p>
                <span class="badge <?php echo $isActive ? 'bg-success' : 'bg-danger'; ?> fs-6 rounded-pill px-3 py-2 shadow-sm">
                    <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            
            <div class="profile-body p-4 p-md-5">
                <h5 class="fw-bold text-primary mb-4 border-bottom pb-2"><i class="bi bi-person-lines-fill me-2"></i>Personal Details</h5>
                
                <div class="row mb-4">
                    <div class="col-sm-6 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1"><i class="bi bi-calendar-date me-1"></i> Date of Birth</div>
                        <div class="fs-6 fw-medium text-dark"><?php echo date('F d, Y', strtotime($stu_profile['date_of_birth'])); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1"><i class="bi bi-gender-ambiguous me-1"></i> Gender</div>
                        <div class="fs-6 fw-medium text-dark"><?php echo htmlspecialchars($stu_profile['gender']); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1"><i class="bi bi-telephone me-1"></i> Contact Number</div>
                        <div class="fs-6 fw-medium text-dark"><?php echo htmlspecialchars($stu_profile['mobile'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1"><i class="bi bi-envelope me-1"></i> Email</div>
                        <div class="fs-6 fw-medium text-dark"><?php echo htmlspecialchars($stu_profile['email'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1"><i class="bi bi-house-door me-1"></i> Address</div>
                        <div class="fs-6 fw-medium text-dark"><?php echo nl2br(htmlspecialchars($stu_profile['address'] ?? 'N/A')); ?></div>
                    </div>
                </div>

                <h5 class="fw-bold text-success mb-4 border-bottom pb-2 mt-2"><i class="bi bi-mortarboard me-2"></i>Academic Details</h5>
                <div class="row mb-4">
                    <div class="col-sm-6 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1">Grade</div>
                        <div class="fs-5 fw-bold text-dark"><?php echo htmlspecialchars($stu_profile['grade'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1">Section</div>
                        <div class="fs-5 fw-bold text-dark"><?php echo htmlspecialchars($stu_profile['section'] ?? 'N/A'); ?></div>
                    </div>
                </div>

                <h5 class="fw-bold text-warning mb-4 border-bottom pb-2 mt-2"><i class="bi bi-calendar-check me-2"></i>Attendance Summary</h5>
                <div class="row mb-4">
                    <div class="col-sm-3 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1">Total Days</div>
                        <div class="fs-5 fw-bold text-dark"><?php echo $total_days; ?></div>
                    </div>
                    <div class="col-sm-3 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1">Present</div>
                        <div class="fs-5 fw-bold text-success"><?php echo $present_days; ?></div>
                    </div>
                    <div class="col-sm-3 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1">Absent</div>
                        <div class="fs-5 fw-bold text-danger"><?php echo $absent_days; ?></div>
                    </div>
                    <div class="col-sm-3 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1">Percentage</div>
                        <div class="fs-5 fw-bold text-primary"><?php echo $attendance_percent; ?>%</div>
                    </div>
                </div>

                <h5 class="fw-bold text-info mb-4 border-bottom pb-2 mt-2"><i class="bi bi-people me-2"></i>Parent / Guardian Information</h5>
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1">Parent Name</div>
                        <div class="fs-6 fw-medium text-dark text-muted fst-italic">N/A</div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="text-uppercase small fw-bold text-muted mb-1">Parent Contact</div>
                        <div class="fs-6 fw-medium text-dark text-muted fst-italic">N/A</div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>
