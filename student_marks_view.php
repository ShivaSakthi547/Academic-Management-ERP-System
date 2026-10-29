<?php
// student_marks_view.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Student') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$student_username = $_SESSION['user_name']; // username is student_id

try {
    // 1. Fetch Student Details
    $stmt_stu = $pdo->prepare("
        SELECT s.id, s.student_name, s.student_id, c.grade, c.section, c.id AS class_id
        FROM students s
        LEFT JOIN class_students cs ON s.id = cs.student_id
        LEFT JOIN classes c ON cs.class_id = c.id
        WHERE s.student_id = ?
    ");
    $stmt_stu->execute([$student_username]);
    $student = $stmt_stu->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Student record not found.'];
        header("Location: dashboard.php");
        exit();
    }

    $stu_id = $student['id'];
    $grade = $student['grade'];
    $section = $student['section'];

    // 2. Fetch Marks
    $stmt_marks = $pdo->prepare("
        SELECT sub.subject_name, sub.subject_code, sm.marks
        FROM subjects sub
        LEFT JOIN student_marks sm ON sub.id = sm.subject_id AND sm.student_id = ?
        WHERE sub.class_id = ?
    ");
    $stmt_marks->execute([$stu_id, $student['class_id']]);
    $marks_data = $stmt_marks->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_marks = 0;
    $max_possible = count($marks_data) * 100;
    foreach ($marks_data as $md) {
        $total_marks += floatval($md['marks']);
    }
    
    $percentage = $max_possible > 0 ? ($total_marks / $max_possible) * 100 : 0;
    
    // Performance Level
    $performance_level = 'Poor';
    if ($percentage >= 90) $performance_level = 'Excellent';
    elseif ($percentage >= 75) $performance_level = 'Good';
    elseif ($percentage >= 50) $performance_level = 'Average';

    // 3. Calculate Rank within Grade and Section
    $rank = null;
    if ($grade && $section) {
        $stmt_rank = $pdo->prepare("
            SELECT s.id AS st_id, IFNULL(SUM(sm.marks), 0) AS total_score
            FROM students s
            JOIN class_students cs ON s.id = cs.student_id
            JOIN classes c ON cs.class_id = c.id
            LEFT JOIN student_marks sm ON s.id = sm.student_id
            WHERE c.grade = ? AND c.section = ?
            GROUP BY s.id
            ORDER BY total_score DESC
        ");
        $stmt_rank->execute([$grade, $section]);
        $ranked_students = $stmt_rank->fetchAll(PDO::FETCH_ASSOC);
        
        $current_rank = 1;
        foreach ($ranked_students as $rs) {
            if ($rs['st_id'] == $stu_id) {
                $rank = $current_rank;
                break;
            }
            $current_rank++;
        }
    }

    // 4. Report Card Check
    $stmt_rep = $pdo->prepare("SELECT pdf_path FROM report_cards WHERE student_id = ? ORDER BY generated_at DESC LIMIT 1");
    $stmt_rep->execute([$stu_id]);
    $report_path = $stmt_rep->fetchColumn();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Marks - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .summary-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
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
            
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <h1 class="h2">My Academic Marks</h1>
                <?php if ($report_path): ?>
                    <a href="report_card_preview.php?id=<?php echo $stu_id; ?>&auto_download=1" target="_blank" class="btn btn-success fw-bold shadow-sm">
                        <i class="bi bi-download me-2"></i>Download Report Card
                    </a>
                <?php endif; ?>
            </div>

            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card summary-card p-3 text-center h-100" style="border-bottom: 4px solid #0d6efd;">
                        <p class="text-uppercase small fw-bold text-muted mb-1">Total Marks</p>
                        <h3 class="fw-bold text-primary mb-0"><?php echo number_format($total_marks, 2); ?> <span class="fs-6 text-muted">/ <?php echo $max_possible; ?></span></h3>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card summary-card p-3 text-center h-100" style="border-bottom: 4px solid #198754;">
                        <p class="text-uppercase small fw-bold text-muted mb-1">Percentage</p>
                        <h3 class="fw-bold text-success mb-0"><?php echo number_format($percentage, 2); ?>%</h3>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card summary-card p-3 text-center h-100" style="border-bottom: 4px solid #0dcaf0;">
                        <p class="text-uppercase small fw-bold text-muted mb-1">Class Rank</p>
                        <h3 class="fw-bold text-info mb-0"><?php echo $rank ? "#" . $rank : "N/A"; ?></h3>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card summary-card p-3 text-center h-100" style="border-bottom: 4px solid #ffc107;">
                        <p class="text-uppercase small fw-bold text-muted mb-1">Performance Level</p>
                        <h3 class="fw-bold text-warning mb-0"><?php echo $performance_level; ?></h3>
                    </div>
                </div>
            </div>

            <div class="card summary-card">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-journal-text text-primary me-2"></i>Subject-wise Marks</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="py-3 ps-4 text-uppercase small text-muted">Subject Name</th>
                                    <th class="py-3 pe-4 text-end text-uppercase small text-muted">Marks Obtained</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($marks_data)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No subjects assigned or marks available.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($marks_data as $md): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($md['subject_name']); ?></td>
                                            <td class="pe-4 text-end fw-bold <?php echo (is_null($md['marks'])) ? 'text-muted' : 'text-primary'; ?>">
                                                <?php echo is_null($md['marks']) ? 'Not Entered' : number_format($md['marks'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
