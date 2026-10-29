<?php
// hall_ticket.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? ''; // For student, this is student_id

$exam_id = intval($_GET['exam_id'] ?? 0);
$student_internal_id = intval($_GET['student_id'] ?? 0);

if ($exam_id <= 0 || $student_internal_id <= 0) {
    die("Invalid Parameters.");
}

try {
    // Validate Access
    if ($userRole === 'Student') {
        // Find internal ID for this student
        $stmt_stu = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
        $stmt_stu->execute([$userName]);
        $my_internal_id = $stmt_stu->fetchColumn();

        if ($student_internal_id != $my_internal_id) {
            die("Access Denied. You can only view your own hall ticket.");
        }
    }

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

    if ($userRole === 'Staff' || $userRole === 'Student') {
        if (!$exam['is_published']) {
            die("Access Denied. Exam is not published yet.");
        }
    }

    // Fetch Student Info and Allotment
    $stmt_ha = $pdo->prepare("
        SELECT ha.*, s.student_id as roll_no, s.student_name, s.photo, eh.hall_name, eh.hall_code 
        FROM hall_allotments ha 
        JOIN students s ON ha.student_id = s.id 
        JOIN exam_halls eh ON ha.hall_id = eh.id 
        WHERE ha.exam_id = ? AND s.id = ?
    ");
    $stmt_ha->execute([$exam_id, $student_internal_id]);
    $allotment = $stmt_ha->fetch(PDO::FETCH_ASSOC);

    if (!$allotment) {
        die("No hall allotment found for this student for this exam.");
    }

    // Fetch Institution Info
    $stmt_inst = $pdo->query("SELECT * FROM institution_profile LIMIT 1");
    $inst = $stmt_inst->fetch(PDO::FETCH_ASSOC);
    
    $school_name = $inst['school_name'] ?? 'SMART CAMPUS MATRIC HIGHER SECONDARY SCHOOL';
    $school_address = $inst['address'] ?? '123 Education Street, Knowledge City';
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

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hall Ticket - <?php echo htmlspecialchars($allotment['student_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f0f2f5; }
        #pdf-container {
            width: 210mm;
            /* Half page for a hall ticket usually looks better, but we can do full A4 */
            min-height: 148mm; 
            margin: 20px auto;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 15mm;
            border: 2px solid #0d6efd;
            border-radius: 10px;
            position: relative;
        }
        .header-section { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .logo-img { max-height: 70px; }
        .school-title { font-weight: 800; font-size: 22px; text-transform: uppercase; margin-bottom: 2px;}
        .hall-ticket-title { font-weight: 800; text-align: center; margin-bottom: 20px; text-decoration: underline; text-transform: uppercase; letter-spacing: 2px;}
        .student-photo { width: 100px; height: 120px; object-fit: cover; border: 2px solid #000; padding: 2px; }
        .info-table td { padding: 5px; border: none; font-size: 14px;}
        .info-table td.label { font-weight: 700; width: 40%; }
        .timetable-section th { background-color: #f1f1f1 !important; -webkit-print-color-adjust: exact; }
        .signature-box { border-top: 1px dashed #000; width: 80%; margin: 0 auto; padding-top: 5px; font-weight: 600; font-size: 14px;}
        .seal-box { border: 2px dashed #ccc; width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ccc; margin: 0 auto; font-size: 12px; font-weight: bold;}
        @media print {
            body { background: white; }
            #pdf-container { margin: 0; box-shadow: none; width: 100%; border: 2px solid #000; border-radius: 0; }
            .no-print { display: none !important; }
        }
    </style>

    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

<div class="container text-center py-3 no-print">
    <button class="btn btn-primary" onclick="generatePDF()">Download Hall Ticket PDF</button>
    <button class="btn btn-secondary" onclick="window.close()">Close</button>
</div>

<div id="pdf-container">
    <div class="header-section">
        <div class="row align-items-center">
            <div class="col-2 text-start">
                <img src="<?php echo htmlspecialchars($school_logo); ?>" class="logo-img" alt="Logo" onerror="this.src='uploads/default_logo.png'">
            </div>
            <div class="col-10 text-center pe-5">
                <h2 class="school-title"><?php echo htmlspecialchars($school_name); ?></h2>
                <p class="mb-0 small fw-bold text-muted"><?php echo htmlspecialchars($school_address); ?></p>
            </div>
        </div>
    </div>

    <h4 class="hall-ticket-title">HALL TICKET</h4>

    <div class="row mb-4 align-items-center">
        <div class="col-9">
            <table class="table info-table mb-0">
                <tr><td class="label">Exam Name:</td><td><?php echo htmlspecialchars($exam['exam_name']); ?></td></tr>
                <tr><td class="label">Student Name:</td><td class="fw-bold fs-5 text-uppercase"><?php echo htmlspecialchars($allotment['student_name']); ?></td></tr>
                <tr><td class="label">Student ID / Roll No:</td><td><?php echo htmlspecialchars($allotment['roll_no']); ?></td></tr>
                <tr><td class="label">Class & Section:</td><td><?php echo htmlspecialchars($exam['grade'] . ' - ' . $exam['section']); ?></td></tr>
                <tr><td class="label">Examination Hall:</td><td class="fw-bold text-primary"><?php echo htmlspecialchars($allotment['hall_name'] . ' (' . $allotment['hall_code'] . ')'); ?></td></tr>
            </table>
        </div>
        <div class="col-3 text-end">
            <?php $photo_url = !empty($allotment['photo']) ? $allotment['photo'] : 'uploads/default_avatar.png'; ?>
            <img src="<?php echo htmlspecialchars($photo_url); ?>" class="student-photo" alt="Photo" onerror="this.src='uploads/default_avatar.png'">
        </div>
    </div>

    <table class="table table-bordered table-sm mb-4 align-middle timetable-section">
        <thead class="text-center">
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Subject</th>
                <th>Invigilator Sign</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($timetable as $tt): ?>
                <tr>
                    <td class="text-center"><?php echo date('d-m-Y', strtotime($tt['exam_date'])); ?></td>
                    <td class="text-center"><?php echo date('h:i A', strtotime($tt['start_time'])) . ' to ' . date('h:i A', strtotime($tt['end_time'])); ?></td>
                    <td class="fw-bold px-2"><?php echo htmlspecialchars($tt['subject_name']); ?></td>
                    <td></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="row mt-5 pt-3 align-items-end">
        <div class="col-4 text-center">
            <p class="signature-box">Student Signature</p>
        </div>
        <div class="col-4 text-center">
            <div class="seal-box">SCHOOL SEAL</div>
        </div>
        <div class="col-4 text-center">
            <p class="signature-box">Principal Signature</p>
        </div>
    </div>
    
    <div class="mt-4 text-center small text-muted">
        <strong>Instructions:</strong> Please bring this hall ticket to the examination hall every day. Do not write anything on this hall ticket. Mobile phones and smart watches are strictly prohibited inside the hall.
    </div>
</div>

<script>
    function generatePDF() {
        const element = document.getElementById('pdf-container');
        const opt = {
            margin:       0.2,
            filename:     'HallTicket_<?php echo htmlspecialchars($allotment['roll_no']); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>

</body>
</html>
