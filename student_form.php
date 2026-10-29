<?php
// student_form.php
session_start();

// Protect page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Unauthenticated access. Please log in.'];
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/db.php';

$is_edit = false;
$student = null;
$student_id_param = $_GET['id'] ?? null;

if ($student_id_param !== null) {
    $is_edit = true;
    $id = intval($student_id_param);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            $_SESSION['toasts'] = [['type' => 'danger', 'msg' => 'Student not found.']];
            header("Location: dashboard.php");
            exit();
        }
    } catch (\PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Grades list
$grades = [
    "First Grade", "Second Grade", "Third Grade", "Fourth Grade",
    "Fifth Grade", "Sixth Grade", "Seventh Grade", "Eighth Grade",
    "Ninth Grade", "Tenth Grade", "Eleventh Grade", "Twelfth Grade"
];

// Preserved data in case of insertion failure
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Student' : 'Register Student'; ?> - Academic Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8f9fa;
        }
        .form-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
        }
        .form-header {
            background-color: #0d6efd;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            color: #ffffff;
        }
        .photo-preview {
            max-width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 3px solid #dee2e6;
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
            
            <div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card form-card">
                <div class="form-header p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="fw-bold mb-0">
                            <i class="bi <?php echo $is_edit ? 'bi-person-gear' : 'bi-person-plus-fill'; ?> me-2"></i>
                            <?php echo $is_edit ? 'Edit Student Details' : 'Student Registration Form'; ?>
                        </h3>
                        <p class="mb-0 text-white-50 small">Please fill out all the required information below</p>
                    </div>
                    <?php if (!$is_edit): ?>
                    <div>
                        <button type="button" class="btn btn-light text-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#importStudentsModal">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i>Import Students
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4 p-md-5">

                    <!-- Backend Errors -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" role="alert">
                            <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please resolve the following errors:</h6>
                            <ul class="mb-0 small">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="action.php?action=<?php echo $is_edit ? 'edit_student' : 'add_student'; ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="studentForm">
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <!-- Student Name -->
                            <div class="col-md-6">
                                <label for="student_name" class="form-label fw-semibold text-secondary">Student Name</label>
                                <input type="text" class="form-control" id="student_name" name="student_name" 
                                       placeholder="Enter Full Name" 
                                       value="<?php echo htmlspecialchars($is_edit ? $student['student_name'] : ($form_data['student_name'] ?? '')); ?>" required>
                                <div class="invalid-feedback">Student name is required.</div>
                            </div>

                            <!-- Student ID -->
                            <div class="col-md-6">
                                <label for="student_id" class="form-label fw-semibold text-secondary">Student ID</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" 
                                       placeholder="Enter Unique ID (e.g. STU12345)" 
                                       value="<?php echo htmlspecialchars($is_edit ? $student['student_id'] : ($form_data['student_id'] ?? '')); ?>" required>
                                <div class="invalid-feedback">A unique Student ID is required.</div>
                            </div>

                            <!-- Date of Birth -->
                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label fw-semibold text-secondary">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                       value="<?php echo htmlspecialchars($is_edit ? $student['date_of_birth'] : ($form_data['date_of_birth'] ?? '')); ?>" required>
                                <div class="invalid-feedback">Date of birth is required.</div>
                            </div>

                            <!-- Gender -->
                            <div class="col-md-6">
                                <label for="gender" class="form-label fw-semibold text-secondary">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="" disabled selected>Select Gender</option>
                                    <?php 
                                    $genders = ["Male", "Female", "Other"];
                                    $selected_gender = $is_edit ? $student['gender'] : ($form_data['gender'] ?? '');
                                    foreach ($genders as $g) {
                                        $selected = ($selected_gender === $g) ? 'selected' : '';
                                        echo "<option value=\"$g\" $selected>$g</option>";
                                    }
                                    ?>
                                </select>
                                <div class="invalid-feedback">Please select a gender.</div>
                            </div>

                            <!-- Email Address -->
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold text-secondary">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="e.g. name@school.com" 
                                       value="<?php echo htmlspecialchars($is_edit ? $student['email'] : ($form_data['email'] ?? '')); ?>" required>
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>

                            <!-- Mobile Number -->
                            <div class="col-md-6">
                                <label for="mobile" class="form-label fw-semibold text-secondary">Mobile Number</label>
                                <input type="tel" class="form-control" id="mobile" name="mobile" 
                                       placeholder="e.g. +1234567890" 
                                       value="<?php echo htmlspecialchars($is_edit ? $student['mobile'] : ($form_data['mobile'] ?? '')); ?>" required>
                                <div class="invalid-feedback">Please provide a valid mobile number.</div>
                            </div>

                            <!-- Permanent Address -->
                            <div class="col-12">
                                <label for="address" class="form-label fw-semibold text-secondary">Permanent Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3" 
                                          placeholder="Enter permanent residential address" required><?php echo htmlspecialchars($is_edit ? $student['address'] : ($form_data['address'] ?? '')); ?></textarea>
                                <div class="invalid-feedback">Permanent address is required.</div>
                            </div>

                            <!-- Grade -->
                            <div class="col-md-6">
                                <label for="grade" class="form-label fw-semibold text-secondary">Grade</label>
                                <select class="form-select" id="grade" name="grade" required>
                                    <option value="" disabled selected>Select Grade</option>
                                    <?php 
                                    $selected_grade = $is_edit ? $student['grade'] : ($form_data['grade'] ?? '');
                                    foreach ($grades as $g) {
                                        $selected = ($selected_grade === $g) ? 'selected' : '';
                                        echo "<option value=\"$g\" $selected>$g</option>";
                                    }
                                    ?>
                                </select>
                                <div class="invalid-feedback">Please select a grade.</div>
                            </div>



                            <!-- Photo Preview (If Editing) -->
                            <?php if ($is_edit): ?>
                                <div class="col-12 mt-4 text-center">
                                    <p class="mb-2 fw-semibold text-secondary">Current Student Photo Preview</p>
                                    <img id="currentPhoto" src="<?php echo htmlspecialchars($student['photo']); ?>" alt="Current Photo" class="photo-preview mb-2 shadow-sm d-inline-block">
                                </div>
                            <?php endif; ?>

                            <!-- Photo Upload -->
                            <div class="col-12">
                                <label for="photo" class="form-label fw-semibold text-secondary">
                                    <?php echo $is_edit ? 'Upload New Photo (Optional)' : 'Student Photo Upload'; ?>
                                </label>
                                <input type="file" class="form-control" id="photo" name="photo" 
                                       accept=".png, .jpg, .jpeg" <?php echo $is_edit ? '' : 'required'; ?> onchange="previewImage(this)">
                                <div class="form-text text-muted small">Only JPG, JPEG, and PNG files are allowed. Maximum file size: 5MB.</div>
                                <div class="invalid-feedback">A student photo is required.</div>
                                
                                <!-- Preview container for newly uploaded image -->
                                <div class="mt-3 text-center d-none" id="previewContainer">
                                    <p class="mb-2 fw-semibold text-secondary">Selected Photo Preview</p>
                                    <img id="imagePreview" src="#" alt="New Image Preview" class="photo-preview shadow-sm d-inline-block">
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="dashboard.php" class="btn btn-outline-secondary px-4 py-2">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-save me-1"></i><?php echo $is_edit ? 'Save Changes' : 'Register Student'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
        </main>
    </div>
</div>


<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Bootstrap Form Validation
    (() => {
        'use strict'
        const form = document.getElementById('studentForm')
        
        form.addEventListener('submit', event => {
            // Check HTML5 constraints
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            
            // Custom validations for file size
            const photoInput = document.getElementById('photo');
            if (photoInput.files.length > 0) {
                const file = photoInput.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (file.size > maxSize) {
                    alert("The file you selected is too large (" + (file.size / (1024 * 1024)).toFixed(2) + "MB). The maximum allowed size is 5MB.");
                    event.preventDefault()
                    event.stopPropagation()
                }
            }

            form.classList.add('was-validated')
        }, false)
    })()

    // Preview uploaded file
    function previewImage(input) {
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('imagePreview');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                previewContainer.classList.remove('d-none');
            }
            
            reader.readAsDataURL(file);
        } else {
            previewContainer.classList.add('d-none');
        }
    }

    
</script>

<!-- Import Students Modal -->
<div class="modal fade" id="importStudentsModal" tabindex="-1" aria-labelledby="importStudentsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="importStudentsModalLabel"><i class="bi bi-upload me-2"></i>Bulk Student Import</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          
        <!-- Step 1 & 2 -->
        <div class="row mb-4" id="importStep1">
            <div class="col-md-6 border-end">
                <h6 class="fw-bold text-secondary mb-3">Step 1: Download Sample</h6>
                <p class="text-muted small">Download the template to ensure your data matches the required format.</p>
                <button type="button" class="btn btn-outline-primary" onclick="downloadSampleExcel()">
                    <i class="bi bi-download me-2"></i>Download Sample Excel
                </button>
            </div>
            <div class="col-md-6 ps-md-4">
                <h6 class="fw-bold text-secondary mb-3">Step 2: Upload Excel File</h6>
                <div class="mb-3">
                    <label for="excelFile" class="form-label small">Allowed formats: .xls, .xlsx</label>
                    <input class="form-control" type="file" id="excelFile" accept=".xls,.xlsx">
                </div>
                <button type="button" class="btn btn-primary w-100" id="previewBtn">
                    <i class="bi bi-eye me-2"></i>Preview Data
                </button>
            </div>
        </div>

        <!-- Step 3: Preview -->
        <div id="importStepPreview" class="d-none">
            <hr>
            <h6 class="fw-bold text-secondary mb-3">Step 3: Preview Data</h6>
            
            <div class="row text-center mb-3 g-2">
                <div class="col"><div class="p-2 border rounded bg-light"><div class="small text-muted">Total</div><div class="fw-bold fs-5" id="statTotal">0</div></div></div>
                <div class="col"><div class="p-2 border rounded bg-success text-white"><div class="small text-white-50">Valid</div><div class="fw-bold fs-5" id="statValid">0</div></div></div>
                <div class="col"><div class="p-2 border rounded bg-danger text-white"><div class="small text-white-50">Invalid</div><div class="fw-bold fs-5" id="statInvalid">0</div></div></div>
                <div class="col"><div class="p-2 border rounded bg-warning"><div class="small text-dark-50">Duplicates</div><div class="fw-bold fs-5" id="statDuplicates">0</div></div></div>
            </div>

            <div class="table-responsive" style="max-height: 350px;">
                <table class="table table-bordered table-hover table-sm text-center align-middle" id="previewTable">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>DOB</th>
                            <th>Gender</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Grade</th>
                            <th>Status/Errors</th>
                        </tr>
                    </thead>
                    <tbody id="previewTableBody">
                    </tbody>
                </table>
            </div>
        </div>
      </div>
      <div class="modal-footer d-none" id="importStep4">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success fw-bold px-4" id="importConfirmBtn">
            <i class="bi bi-check-circle me-2"></i>Import All Valid Students
        </button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
    let globalParsedData = [];

    function downloadSampleExcel() {
        const worksheetData = [
            ["Student Name", "Student ID", "DOB", "Gender", "Email", "Phone", "Address", "Grade", "Status"],
            ["John Doe", "STU001", "2010-05-15", "Male", "john@example.com", "1234567890", "123 Main St", "Tenth Grade", "Active"],
            ["Jane Smith", "STU002", "2011-08-20", "Female", "jane@example.com", "0987654321", "456 Oak Ave", "Ninth Grade", "Active"]
        ];
        
        const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
        
        // Auto-size columns slightly
        worksheet['!cols'] = [
            {wch: 15}, {wch: 12}, {wch: 12}, {wch: 8}, {wch: 20}, {wch: 12}, {wch: 20}, {wch: 15}, {wch: 10}
        ];

        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Sample");

        XLSX.writeFile(workbook, "Student_Import_Sample.xlsx");
    }

    document.getElementById('previewBtn').addEventListener('click', function() {
        const fileInput = document.getElementById('excelFile');
        if (!fileInput.files.length) {
            alert("Please select an Excel file to upload.");
            return;
        }

        const file = fileInput.files[0];
        const validExts = ['xls', 'xlsx'];
        const ext = file.name.split('.').pop().toLowerCase();
        
        if (!validExts.includes(ext)) {
            alert("Invalid file type. Please upload .xls or .xlsx only.");
            return;
        }
        
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Reading...';
        this.disabled = true;

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, {type: 'array', cellDates: true, dateNF: 'yyyy-mm-dd'});
                
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                
                // Convert to JSON
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { raw: false, dateNF: 'yyyy-mm-dd' });
                
                if (jsonData.length === 0) {
                    alert("The uploaded Excel file is empty.");
                    resetPreviewBtn();
                    return;
                }

                // Send to backend for preview/validation
                fetch('import_students.php?action=preview', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(jsonData)
                })
                .then(response => response.json())
                .then(result => {
                    resetPreviewBtn();
                    if (result.status === 'error') {
                        alert(result.message);
                        return;
                    }
                    
                    renderPreview(result.data, result.summary);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("An error occurred while communicating with the server.");
                    resetPreviewBtn();
                });

            } catch(err) {
                console.error(err);
                alert("Error reading Excel file. Make sure it's a valid format.");
                resetPreviewBtn();
            }
        };
        reader.readAsArrayBuffer(file);
    });
    
    function resetPreviewBtn() {
        const btn = document.getElementById('previewBtn');
        btn.innerHTML = '<i class="bi bi-eye me-2"></i>Preview Data';
        btn.disabled = false;
    }

    function renderPreview(rows, summary) {
        document.getElementById('importStepPreview').classList.remove('d-none');
        document.getElementById('importStep4').classList.remove('d-none');
        
        document.getElementById('statTotal').textContent = summary.total;
        document.getElementById('statValid').textContent = summary.valid;
        document.getElementById('statInvalid').textContent = summary.invalidRows;
        document.getElementById('statDuplicates').textContent = summary.duplicatesId + summary.duplicatesEmail;
        
        globalParsedData = rows; // Save for import
        
        const tbody = document.getElementById('previewTableBody');
        tbody.innerHTML = '';
        
        rows.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = item.isValid ? 'table-success' : 'table-danger';
            
            const row = item.row;
            tr.innerHTML = `
                <td>${escapeHtml(row['Student Name'] || '')}</td>
                <td>${escapeHtml(row['Student ID'] || '')}</td>
                <td>${escapeHtml(row['DOB'] || '')}</td>
                <td>${escapeHtml(row['Gender'] || '')}</td>
                <td>${escapeHtml(row['Email'] || '')}</td>
                <td>${escapeHtml(row['Phone'] || '')}</td>
                <td>${escapeHtml(row['Grade'] || '')}</td>
                <td class="${item.isValid ? 'text-success' : 'text-danger fw-bold'}">${item.isValid ? 'Valid' : escapeHtml(item.errors)}</td>
            `;
            tbody.appendChild(tr);
        });
        
        if (summary.valid === 0) {
            document.getElementById('importConfirmBtn').disabled = true;
        } else {
            document.getElementById('importConfirmBtn').disabled = false;
        }
    }
    
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
             .toString()
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    document.getElementById('importConfirmBtn').addEventListener('click', function() {
        if (globalParsedData.length === 0) return;
        
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Importing...';
        this.disabled = true;
        
        fetch('import_students.php?action=import', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(globalParsedData)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                window.location.href = 'view_students.php';
            } else {
                alert(result.message || 'Import failed.');
                this.innerHTML = '<i class="bi bi-check-circle me-2"></i>Import All Valid Students';
                this.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("An error occurred during import.");
            this.innerHTML = '<i class="bi bi-check-circle me-2"></i>Import All Valid Students';
            this.disabled = false;
        });
    });
</script>

<?php include_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>
