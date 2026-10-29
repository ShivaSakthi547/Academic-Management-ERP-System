<?php
// exam_reports.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthorized access.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

try {
    // Total Exams
    $stmt = $pdo->query("SELECT COUNT(*) FROM exams");
    $total_exams = $stmt->fetchColumn();

    // Total Students Appearing in Exams (count of distinct students in hall allotments)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM hall_allotments");
    $students_appearing = $stmt->fetchColumn();

    // Hall Utilization (Total Allotted vs Total Capacity of used halls)
    $stmt = $pdo->query("
        SELECT 
            SUM(ha.allotted_count) as total_allotted,
            SUM(eh.capacity) as total_capacity
        FROM (
            SELECT hall_id, COUNT(*) as allotted_count 
            FROM hall_allotments 
            GROUP BY hall_id
        ) ha
        JOIN exam_halls eh ON ha.hall_id = eh.id
    ");
    $hall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_allotted = $hall_stats['total_allotted'] ?? 0;
    $total_capacity = $hall_stats['total_capacity'] ?? 1; // avoid division by zero
    $hall_utilization = ($total_allotted > 0) ? round(($total_allotted / $total_capacity) * 100, 1) : 0;

    // Class-wise Exam Statistics
    $stmt = $pdo->query("
        SELECT c.class_name, c.grade, c.section, COUNT(e.id) as exam_count
        FROM classes c
        LEFT JOIN exams e ON c.id = e.class_id
        WHERE c.status = 1 AND c.deleted_at IS NULL
        GROUP BY c.id
        ORDER BY c.grade, c.section
    ");
    $class_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chart_labels = [];
    $chart_data = [];
    foreach ($class_stats as $stat) {
        $chart_labels[] = $stat['class_name'] . ' (' . $stat['grade'] . '-' . $stat['section'] . ')';
        $chart_data[] = $stat['exam_count'];
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Analytics - Academic Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8f9fa; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); }
        .chart-container { position: relative; height: 60vh; width: 100%; }
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
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Exam Analytics</h1>
            </div>

            <!-- Stats Overview -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card stat-card p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-secondary fw-bold text-uppercase mb-1 small">Total Exams Conducted</p>
                                <h2 class="fw-bold text-primary mb-0"><?php echo $total_exams; ?></h2>
                            </div>
                            <div class="fs-1 text-primary opacity-50"><i class="bi bi-journal-text"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stat-card p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-secondary fw-bold text-uppercase mb-1 small">Students Appearing</p>
                                <h2 class="fw-bold text-success mb-0"><?php echo $students_appearing; ?></h2>
                            </div>
                            <div class="fs-1 text-success opacity-50"><i class="bi bi-people-fill"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stat-card p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-secondary fw-bold text-uppercase mb-1 small">Average Hall Utilization</p>
                                <h2 class="fw-bold text-info mb-0"><?php echo $hall_utilization; ?>%</h2>
                            </div>
                            <div class="fs-1 text-info opacity-50"><i class="bi bi-building"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Class-wise Exam Chart -->
            <div class="card stat-card p-4 mb-4">
                <h5 class="fw-bold mb-4">Class-wise Exam Distribution</h5>
                <div class="chart-container">
                    <canvas id="classExamChart"></canvas>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const ctx = document.getElementById('classExamChart').getContext('2d');
    const chartLabels = <?php echo json_encode($chart_labels); ?>;
    const chartData = <?php echo json_encode($chart_data); ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Number of Exams',
                data: chartData,
                backgroundColor: 'rgba(13, 110, 253, 0.7)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
</script>
</body>
</html>
