<?php
// exam_schedule_pdf.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? ''; // For student, this is student_id

$exam_id = intval($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) {
    die("Invalid Exam ID.");
}

try {
    // Fetch Exam Info
    $stmt = $pdo->prepare("
        SELECT e.*, c.class_name, c.grade, c.section 
        FROM exams e 
        JOIN classes c ON e.class_id = c.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        die("Exam not found.");
    }

    $class_id = $exam['class_id'];

    // Access Control Validation
    if ($userRole === 'Student') {
        // Find student's class
        $stmt_stu = $pdo->prepare("
            SELECT cs.class_id 
            FROM students s 
            JOIN class_students cs ON s.id = cs.student_id 
            WHERE s.student_id = ?
        ");
        $stmt_stu->execute([$userName]);
        $student_class = $stmt_stu->fetchColumn();

        if ($student_class != $class_id || !$exam['is_published']) {
            die("Access Denied.");
        }
    } elseif ($userRole === 'Staff') {
        if (!$exam['is_published']) {
            die("Access Denied. Exam is not published yet.");
        }
    }

    // Fetch Institution Info
    $stmt_inst = $pdo->query("SELECT * FROM institution_profile LIMIT 1");
    $inst = $stmt_inst->fetch(PDO::FETCH_ASSOC);
    
    $school_name = $inst['school_name'] ?? 'SMART CAMPUS MATRIC HIGHER SECONDARY SCHOOL';
    $school_address = $inst['address'] ?? '123 Education Street, Knowledge City';
    $school_phone = $inst['phone'] ?? '+1 234 567 8900';
    $school_logo = !empty($inst['logo_path']) ? $inst['logo_path'] : 'uploads/default_logo.png';

    // Fetch Timetable
    $stmt_tt = $pdo->prepare("
        SELECT et.*, s.subject_name 
        FROM exam_timetable et 
        JOIN subjects s ON et.subject_id = s.id 
        WHERE et.exam_id = ?
        ORDER BY et.exam_date ASC, et.start_time ASC
    ");
    $stmt_tt->execute([$exam_id]);
    $timetable = $stmt_tt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Hall Allotment
    // If student, fetch only their own allotment
    $allotment = [];
    if ($userRole === 'Student') {
        $stmt_ha = $pdo->prepare("
            SELECT ha.*, s.student_id as roll_no, s.student_name, eh.hall_name, eh.hall_code 
            FROM hall_allotments ha 
            JOIN students s ON ha.student_id = s.id 
            JOIN exam_halls eh ON ha.hall_id = eh.id 
            WHERE ha.exam_id = ? AND s.student_id = ?
        ");
        $stmt_ha->execute([$exam_id, $userName]);
        $allotment = $stmt_ha->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt_ha = $pdo->prepare("
            SELECT ha.*, s.student_id as roll_no, s.student_name, eh.hall_name, eh.hall_code 
            FROM hall_allotments ha 
            JOIN students s ON ha.student_id = s.id 
            JOIN exam_halls eh ON ha.hall_id = eh.id 
            WHERE ha.exam_id = ?
            ORDER BY eh.hall_name, s.student_id
        ");
        $stmt_ha->execute([$exam_id]);
        $allotment = $stmt_ha->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Schedule PDF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f0f2f5; }
        #pdf-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 20mm;
        }
        .header-section { border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 20px; text-align: center; }
        .logo-img { max-height: 70px; }
        .school-title { font-weight: 800; font-size: 24px; text-transform: uppercase; margin-bottom: 5px;}
        .section-title { font-weight: 700; background-color: #f8f9fa; padding: 5px 10px; margin-bottom: 15px; border-left: 4px solid #0d6efd; }
        .table th { background-color: #f1f1f1 !important; -webkit-print-color-adjust: exact; }
        @media print {
            body { background: white; }
            #pdf-container { margin: 0; box-shadow: none; width: 100%; padding: 10mm; }
            .no-print { display: none !important; }
        }
    </style>

    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

<div class="container text-center py-3 no-print">
    <button class="btn btn-primary" onclick="generatePDF()">Download PDF</button>
    <button class="btn btn-secondary" onclick="window.close()">Close</button>
</div>

<div id="pdf-container">
    <div class="header-section">
        <img src="<?php echo htmlspecialchars($school_logo); ?>" class="logo-img mb-2" alt="Logo" onerror="this.src='uploads/default_logo.png'">
        <h2 class="school-title"><?php echo htmlspecialchars($school_name); ?></h2>
        <p class="mb-0 small"><?php echo htmlspecialchars($school_address); ?> | Phone: <?php echo htmlspecialchars($school_phone); ?></p>
    </div>

    <h3 class="text-center fw-bold mb-4 text-uppercase">Examination Schedule</h3>

    <div class="row mb-4">
        <div class="col-6">
            <p class="mb-1"><strong>Exam Name:</strong> <?php echo htmlspecialchars($exam['exam_name']); ?></p>
            <p class="mb-1"><strong>Exam Type:</strong> <?php echo htmlspecialchars($exam['exam_type']); ?></p>
        </div>
        <div class="col-6 text-end">
            <p class="mb-1"><strong>Class:</strong> <?php echo htmlspecialchars($exam['class_name'] . ' (' . $exam['grade'] . '-' . $exam['section'] . ')'); ?></p>
            <p class="mb-1"><strong>Date:</strong> <?php echo date('d M Y', strtotime($exam['start_date'])) . ' to ' . date('d M Y', strtotime($exam['end_date'])); ?></p>
        </div>
    </div>

    <div class="section-title">Timetable</div>
    <table class="table table-bordered table-sm mb-4 align-middle">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Date</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($timetable as $tt): ?>
                <tr>
                    <td class="fw-bold"><?php echo htmlspecialchars($tt['subject_name']); ?></td>
                    <td><?php echo date('d-M-Y', strtotime($tt['exam_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($tt['start_time'])) . ' to ' . date('h:i A', strtotime($tt['end_time'])); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($timetable)): ?>
                <tr><td colspan="3" class="text-center">No timetable available.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">Hall Allotment</div>
    <table class="table table-bordered table-sm align-middle">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Hall Name</th>
                <th>Hall Code</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allotment as $al): ?>
                <tr>
                    <td><?php echo htmlspecialchars($al['roll_no']); ?></td>
                    <td><?php echo htmlspecialchars($al['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($al['hall_name']); ?></td>
                    <td><?php echo htmlspecialchars($al['hall_code']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($allotment)): ?>
                <tr><td colspan="4" class="text-center">No hall allotment available.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-5 pt-5 row">
        <div class="col-6 text-center">
            <p class="mb-0 border-top d-inline-block pt-2 px-4 border-dark">Class Teacher Signature</p>
        </div>
        <div class="col-6 text-center">
            <p class="mb-0 border-top d-inline-block pt-2 px-4 border-dark">Principal Signature</p>
        </div>
    </div>
</div>

<script>
    function generatePDF() {
        const element = document.getElementById('pdf-container');
        const opt = {
            margin:       0,
            filename:     'Exam_Schedule_<?php echo htmlspecialchars($exam['class_name']); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>

</body>
</html>
