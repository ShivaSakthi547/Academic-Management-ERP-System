<?php
// action.php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/Mailer.php';
$mailer = new Mailer($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';

switch ($action) {
    // ==========================================
    // AUTHENTICATION
    // ==========================================
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: register.php");
            exit();
        }
        
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $address = trim($_POST['address'] ?? '');
        // Registration is locked to Staff only — ignore any submitted role value
        $role = 'Staff';
        
        if (empty($username) || empty($full_name) || empty($email) || empty($dob) || empty($address)) {
            $_SESSION['error'] = 'All fields are required.';
            $_SESSION['form_data'] = $_POST;
            header("Location: register.php");
            exit();
        }
        
        try {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'Username already taken.';
                $_SESSION['form_data'] = $_POST;
                header("Location: register.php");
                exit();
            }

            $password_hashed = password_hash(date('d-m-Y', strtotime($dob)), PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active, status, full_name, email, dob, address) VALUES (?, ?, ?, 0, 'Pending', ?, ?, ?, ?)");
            $stmt->execute([$username, $password_hashed, $role, $full_name, $email, $dob, $address]);
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Your registration is submitted for admin approval.'];
            header("Location: login.php");
            exit();
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error during registration.';
            $_SESSION['form_data'] = $_POST;
            header("Location: register.php");
            exit();
        }
        break;

    case 'approve_user':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            header("Location: user_approvals.php?status=Pending");
            exit();
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'Pending'");
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            if ($u) {
                $pdo->beginTransaction();
                
                if ($u['role'] === 'Student') {
                    $stu_id = $u['username'];
                    $stmt_stu = $pdo->prepare("INSERT INTO students (student_id, student_name, date_of_birth, email, address, role, photo, gender, grade, mobile) VALUES (?, ?, ?, ?, ?, 'Student', 'uploads/default_avatar.png', '', '', '')");
                    $stmt_stu->execute([$stu_id, $u['full_name'], $u['dob'], $u['email'], $u['address']]);
                } elseif ($u['role'] === 'Staff') {
                    $rand_num = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    $stf_id = "STF-{$rand_num}";
                    $stmt_stf = $pdo->prepare("INSERT INTO staff (staff_id, staff_name, dob, address, email, photo, status) VALUES (?, ?, ?, ?, ?, 'uploads/default_avatar.png', 1)");
                    $stmt_stf->execute([$stf_id, $u['username'], $u['dob'], $u['address'], $u['email']]);
                } elseif ($u['role'] === 'Admin') {
                    $rand_num = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    $adm_id = "ADM-{$rand_num}";
                    $stmt_adm = $pdo->prepare("INSERT INTO admins (admin_id, admin_name, dob, address, email, photo, status) VALUES (?, ?, ?, ?, ?, 'uploads/default_avatar.png', 1)");
                    $stmt_adm->execute([$adm_id, $u['username'], $u['dob'], $u['address'], $u['email']]);
                }
                
                $stmt_upd = $pdo->prepare("UPDATE users SET status = 'Approved', is_active = 1 WHERE id = ?");
                $stmt_upd->execute([$id]);
                $pdo->commit();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'User approved successfully.'];
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Error approving user.'];
        }
        header("Location: user_approvals.php?status=Pending");
        exit();
        break;

    case 'reject_user':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = $_GET['id'] ?? '';
        if (!empty($id)) {
            try {
                $stmt_upd = $pdo->prepare("UPDATE users SET status = 'Rejected', is_active = 0 WHERE id = ? AND status = 'Pending'");
                $stmt_upd->execute([$id]);
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'User rejected.'];
            } catch (\PDOException $e) {
                $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Error rejecting user.'];
            }
        }
        header("Location: user_approvals.php?status=Pending");
        exit();
        break;
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: login.php");
            exit();
        }
        
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = trim($_POST['role'] ?? '');
        
        if (empty($username) || empty($password) || empty($role)) {
            $_SESSION['error'] = 'All fields are required.';
            header("Location: login.php");
            exit();
        }
        
        try {
            // ==========================================
            // SYNC EXISTING ADMINS AND STAFF
            // ==========================================
            // Sync missing Admins
            $stmt_missing_admins = $pdo->query("SELECT admin_name, dob, status FROM admins WHERE admin_name NOT IN (SELECT username FROM users WHERE role = 'Admin')");
            while ($adm = $stmt_missing_admins->fetch()) {
                $hash = password_hash(date('d-m-Y', strtotime($adm['dob'])), PASSWORD_DEFAULT);
                $stmt_ins = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'Admin', ?)");
                $stmt_ins->execute([$adm['admin_name'], $hash, $adm['status']]);
            }

            // Sync missing Staff
            $stmt_missing_staff = $pdo->query("SELECT staff_name, dob, status FROM staff WHERE staff_name NOT IN (SELECT username FROM users WHERE role = 'Staff')");
            while ($stf = $stmt_missing_staff->fetch()) {
                $hash = password_hash(date('d-m-Y', strtotime($stf['dob'])), PASSWORD_DEFAULT);
                $stmt_ins = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'Staff', ?)");
                $stmt_ins->execute([$stf['staff_name'], $hash, $stf['status']]);
            }
            // Sync missing Students
            $stmt_missing_students = $pdo->query("SELECT student_id, date_of_birth, deleted_at FROM students WHERE student_id NOT IN (SELECT username FROM users WHERE role = 'Student')");
            while ($stu = $stmt_missing_students->fetch()) {
                $hash = password_hash(date('d-m-Y', strtotime($stu['date_of_birth'])), PASSWORD_DEFAULT);
                $is_active = $stu['deleted_at'] === null ? 1 : 0;
                $stmt_ins = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'Student', ?)");
                $stmt_ins->execute([$stu['student_id'], $hash, $is_active]);
            }
            // ==========================================

            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
            $stmt->execute([$username, $role]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'Pending') {
                    $_SESSION['error'] = 'Your account is pending admin approval.';
                    header("Location: login.php");
                    exit();
                } elseif ($user['status'] === 'Rejected') {
                    $_SESSION['error'] = 'Your account has been rejected. Please contact admin.';
                    header("Location: login.php");
                    exit();
                } elseif ($user['status'] === 'Approved' && $user['is_active'] == 1) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['show_notification_popup'] = true; // Flag for showing popup once after login
                    
                    $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Welcome back, ' . htmlspecialchars($user['username']) . '!'];
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $_SESSION['error'] = 'Your account is inactive.';
                    header("Location: login.php");
                    exit();
                }
            } else {
                $_SESSION['error'] = 'Invalid username, password, or role.';
                header("Location: login.php");
                exit();
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error during login.';
            header("Location: login.php");
            exit();
        }
        break;

    // ==========================================
    // FORGOT PASSWORD FLOW
    // ==========================================
    case 'send_reset_link':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: forgot_password.php");
            exit();
        }
        
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $_SESSION['error'] = 'Please enter a valid email address.';
            header("Location: forgot_password.php");
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, 
                COALESCE(u.email, s.email, st.email, a.email) as user_email
                FROM users u
                LEFT JOIN students s ON u.username = s.student_id
                LEFT JOIN staff st ON u.username = st.staff_name
                LEFT JOIN admins a ON u.username = a.admin_name
                WHERE u.email = ? OR s.email = ? OR st.email = ? OR a.email = ?
            ");
            $stmt->execute([$email, $email, $email, $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $stmt_otp = $pdo->prepare("INSERT INTO password_otps (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt_otp->execute([$user['user_email'], $token, $expires_at]);

                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                $subject = "Password Reset Link";
                
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $reset_link = "http://{$host}/student_registration/reset_password.php?token={$token}";

                $body = "
                    <h3>Password Reset Request</h3>
                    <p>Hello,</p>
                    <p>You have requested to reset your password. Click the link below to set a new password:</p>
                    <p><a href='{$reset_link}' style='display:inline-block; padding:10px 20px; background-color:#0d6efd; color:#fff; text-decoration:none; border-radius:5px;'>Reset Password</a></p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p>{$reset_link}</p>
                    <p>This link will expire in 15 minutes.</p>
                    <p>If you did not request this, please ignore this email.</p>
                ";
                $mailer->sendEmail($user['user_email'], $subject, $body);

                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'A password reset link has been sent to your email.'];
                header("Location: login.php");
                exit();
            } else {
                $_SESSION['error'] = 'No account found with that email address.';
                header("Location: forgot_password.php");
                exit();
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error occurred while sending reset link.';
            header("Location: forgot_password.php");
            exit();
        }
        break;

    case 'reset_password':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: login.php");
            exit();
        }

        $token = $_POST['token'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            $_SESSION['error'] = 'Invalid reset request.';
            header("Location: login.php");
            exit();
        }

        if (strlen($new_password) < 6 || $new_password !== $confirm_password) {
            $_SESSION['error'] = 'Passwords must match and be at least 6 characters.';
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }

        try {
            // Verify token
            $stmt_tok = $pdo->prepare("SELECT email FROM password_otps WHERE token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt_tok->execute([$token]);
            $record = $stmt_tok->fetch();

            if (!$record) {
                $_SESSION['error'] = 'Invalid or expired reset link.';
                header("Location: forgot_password.php");
                exit();
            }

            $email = $record['email'];

            // Find the user's id
            $stmt = $pdo->prepare("
                SELECT u.id
                FROM users u
                LEFT JOIN students s ON u.username = s.student_id
                LEFT JOIN staff st ON u.username = st.staff_name
                LEFT JOIN admins a ON u.username = a.admin_name
                WHERE u.email = ? OR s.email = ? OR st.email = ? OR a.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email, $email, $email, $email]);
            $user = $stmt->fetch();

            if ($user) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_upd->execute([$hash, $user['id']]);

                // Cleanup used token
                $stmt_del = $pdo->prepare("DELETE FROM password_otps WHERE email = ?");
                $stmt_del->execute([$email]);

                $_SESSION['error'] = ''; // clear error
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Password reset successfully. You can now login.'];
                header("Location: login.php");
                exit();
            } else {
                $_SESSION['error'] = 'User not found.';
                header("Location: forgot_password.php");
                exit();
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error resetting password.';
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
        break;

    // ==========================================
    // ADMIN MANAGEMENT
    // ==========================================
    case 'add_admin':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can perform this action.'];
            header("Location: dashboard.php");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: view_admins.php");
            exit();
        }

        $admin_name = trim($_POST['admin_name'] ?? '');
        $dob        = trim($_POST['dob'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $phone_number  = trim($_POST['phone_number'] ?? '');
        $major_subject = trim($_POST['major_subject'] ?? '');

        if (empty($admin_name) || empty($dob) || empty($address)) {
            $_SESSION['error'] = 'All fields are required.';
            header("Location: admin_form.php");
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$admin_name]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'An account with this name already exists.';
                header("Location: admin_form.php");
                exit();
            }

            $admin_id = '';
            $is_unique = false;
            while (!$is_unique) {
                $rand_num = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                $admin_id = "ADM-{$rand_num}";
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE admin_id = ?");
                $stmt_chk->execute([$admin_id]);
                if ($stmt_chk->fetchColumn() == 0) $is_unique = true;
            }

            $password_hashed = password_hash(date('d-m-Y', strtotime($dob)), PASSWORD_DEFAULT);

            $photoPath = 'uploads/default_avatar.png';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/profile_photos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $newName = uniqid('adm_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                        $photoPath = 'uploads/profile_photos/' . $newName;
                    }
                }
            }

            $pdo->beginTransaction();

            // Step 1: Insert into admins table
            $stmt_admin = $pdo->prepare("INSERT INTO admins (admin_id, admin_name, dob, address, email, phone_number, major_subject, photo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt_admin->execute([$admin_id, $admin_name, $dob, $address, $email, $phone_number, $major_subject, $photoPath]);

            // Step 2: Insert into users table
            $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, is_active, email) VALUES (?, ?, 'Admin', 1, ?)");
            $stmt_user->execute([$admin_name, $password_hashed, $email]);

            $pdo->commit();
            
            if (!empty($email)) {
                $subject = "Admin Account Created Successfully";
                $body = "<h3>Admin Account Created Successfully</h3>
                         <p>Dear $admin_name,</p>
                         <p>Your admin account has been created successfully.</p>
                         <p><strong>Admin ID:</strong> $admin_id<br>
                         <strong>Username:</strong> $admin_name<br>
                         <strong>Login Password:</strong> " . date('d-m-Y', strtotime($dob)) . "</p>
                         <p>Please log in and change your password.</p>";
                $mailer->sendEmail($email, $subject, $body);
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Admin added successfully.'];
            header("Location: view_admins.php");
            exit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = 'Failed to add admin: ' . $e->getMessage();
            header("Location: admin_form.php");
            exit();
        }
        break;

    case 'edit_admin':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can perform this action.'];
            header("Location: dashboard.php");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: view_admins.php");
            exit();
        }

        $id         = intval($_POST['id'] ?? 0);
        $admin_name = trim($_POST['admin_name'] ?? '');
        $dob        = trim($_POST['dob'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $phone_number  = trim($_POST['phone_number'] ?? '');
        $major_subject = trim($_POST['major_subject'] ?? '');

        if (empty($id) || empty($admin_name) || empty($dob) || empty($address)) {
            $_SESSION['error'] = 'All fields are required.';
            header("Location: admin_form.php?id=$id");
            exit();
        }

        try {
            $pdo->beginTransaction();
            $stmt_curr = $pdo->prepare("SELECT admin_name FROM admins WHERE id = ?");
            $stmt_curr->execute([$id]);
            $old_name = $stmt_curr->fetchColumn();

            if (!$old_name) throw new Exception("Admin record not found.");

            $photoUpdateSql = "";
            $params = [$admin_name, $dob, $address, $email, $phone_number, $major_subject];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/profile_photos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $newName = uniqid('adm_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                        $photoPath = 'uploads/profile_photos/' . $newName;
                        $photoUpdateSql = ", photo = ?";
                        $params[] = $photoPath;
                    }
                }
            }
            $params[] = $id;

            $stmt_user = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE username = ? AND role = 'Admin'");
            $password_hashed = password_hash(date('d-m-Y', strtotime($dob)), PASSWORD_DEFAULT);
            $stmt_user->execute([$admin_name, $password_hashed, $old_name]);

            $stmt_admin = $pdo->prepare("UPDATE admins SET admin_name = ?, dob = ?, address = ?, email = ?, phone_number = ?, major_subject = ?$photoUpdateSql WHERE id = ?");
            $stmt_admin->execute($params);

            $pdo->commit();
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Admin updated successfully.'];
            header("Location: view_admins.php");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = 'Update failed: ' . $e->getMessage();
            header("Location: admin_form.php?id=$id");
            exit();
        }
        break;

    case 'delete_admin':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can perform this action.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'active';
        try {
            $pdo->beginTransaction();
            $stmt_name = $pdo->prepare("SELECT admin_name FROM admins WHERE id = ?");
            $stmt_name->execute([$id]);
            $admin_name = $stmt_name->fetchColumn();

            if ($admin_name) {
                $stmt = $pdo->prepare("UPDATE admins SET status = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $stmt_user = $pdo->prepare("UPDATE users SET is_active = 0 WHERE username = ? AND role = 'Admin'");
                $stmt_user->execute([$admin_name]);
                $pdo->commit();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Admin moved to Inactive.'];
            } else {
                throw new Exception("Admin not found.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Action failed: ' . $e->getMessage()];
        }
        header("Location: view_admins.php?filter=" . urlencode($ref));
        exit();
        break;

    case 'restore_admin':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can perform this action.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'inactive';
        try {
            $pdo->beginTransaction();
            $stmt_name = $pdo->prepare("SELECT admin_name FROM admins WHERE id = ?");
            $stmt_name->execute([$id]);
            $admin_name = $stmt_name->fetchColumn();

            if ($admin_name) {
                $stmt = $pdo->prepare("UPDATE admins SET status = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $stmt_user = $pdo->prepare("UPDATE users SET is_active = 1 WHERE username = ? AND role = 'Admin'");
                $stmt_user->execute([$admin_name]);
                $pdo->commit();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Admin restored to Active.'];
            } else {
                throw new Exception("Admin not found.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Action failed: ' . $e->getMessage()];
        }
        header("Location: view_admins.php?filter=" . urlencode($ref));
        exit();
        break;

    // ==========================================
    // STAFF MANAGEMENT
    // ==========================================
    case 'add_staff':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can perform this action.'];
            header("Location: dashboard.php");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: view_staff.php");
            exit();
        }

        $staff_name = trim($_POST['staff_name'] ?? '');
        $dob        = trim($_POST['dob'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $phone_number  = trim($_POST['phone_number'] ?? '');
        $major_subject = trim($_POST['major_subject'] ?? '');

        if (empty($staff_name) || empty($dob) || empty($address)) {
            $_SESSION['error'] = 'All fields are required.';
            header("Location: staff_form.php");
            exit();
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$staff_name]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'An account with this name already exists.';
                header("Location: staff_form.php");
                exit();
            }

            $staff_id = '';
            $is_unique = false;
            while (!$is_unique) {
                $rand_num = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                $staff_id = "STF-{$rand_num}";
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE staff_id = ?");
                $stmt_chk->execute([$staff_id]);
                if ($stmt_chk->fetchColumn() == 0) $is_unique = true;
            }

            $password_hashed = password_hash(date('d-m-Y', strtotime($dob)), PASSWORD_DEFAULT);

            $photoPath = 'uploads/default_avatar.png';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/profile_photos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $newName = uniqid('stf_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                        $photoPath = 'uploads/profile_photos/' . $newName;
                    }
                }
            }

            $pdo->beginTransaction();

            // Step 1: Insert into staff table
            $stmt_staff = $pdo->prepare("INSERT INTO staff (staff_id, staff_name, dob, address, email, phone_number, major_subject, photo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt_staff->execute([$staff_id, $staff_name, $dob, $address, $email, $phone_number, $major_subject, $photoPath]);

            // Step 2: Insert into users table
            $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, is_active, email) VALUES (?, ?, 'Staff', 1, ?)");
            $stmt_user->execute([$staff_name, $password_hashed, $email]);

            $pdo->commit();
            
            if (!empty($email)) {
                $subject = "Staff Account Created Successfully";
                $body = "<h3>Staff Account Created Successfully</h3>
                         <p>Dear $staff_name,</p>
                         <p>Your staff account has been created successfully.</p>
                         <p><strong>Staff ID:</strong> $staff_id<br>
                         <strong>Major Subject:</strong> $major_subject<br>
                         <strong>Username:</strong> $staff_name<br>
                         <strong>Login Password:</strong> " . date('d-m-Y', strtotime($dob)) . "</p>
                         <p>Please log in and change your password.</p>";
                $mailer->sendEmail($email, $subject, $body);
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Staff added successfully.'];
            header("Location: view_staff.php");
            exit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = 'Failed to add staff: ' . $e->getMessage();
            header("Location: staff_form.php");
            exit();
        }
        break;

    case 'edit_staff':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can perform this action.'];
            header("Location: dashboard.php");
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: view_staff.php");
            exit();
        }

        $id         = intval($_POST['id'] ?? 0);
        $staff_name = trim($_POST['staff_name'] ?? '');
        $dob        = trim($_POST['dob'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $phone_number  = trim($_POST['phone_number'] ?? '');
        $major_subject = trim($_POST['major_subject'] ?? '');

        if (empty($id) || empty($staff_name) || empty($dob) || empty($address)) {
            $_SESSION['error'] = 'All fields are required.';
            header("Location: staff_form.php?id=$id");
            exit();
        }

        try {
            $pdo->beginTransaction();
            $stmt_curr = $pdo->prepare("SELECT staff_name FROM staff WHERE id = ?");
            $stmt_curr->execute([$id]);
            $old_name = $stmt_curr->fetchColumn();

            if (!$old_name) throw new Exception("Staff record not found.");

            $photoUpdateSql = "";
            $params = [$staff_name, $dob, $address, $email, $phone_number, $major_subject];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/profile_photos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $newName = uniqid('stf_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                        $photoPath = 'uploads/profile_photos/' . $newName;
                        $photoUpdateSql = ", photo = ?";
                        $params[] = $photoPath;
                    }
                }
            }
            $params[] = $id;

            $stmt_user = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE username = ? AND role = 'Staff'");
            $password_hashed = password_hash(date('d-m-Y', strtotime($dob)), PASSWORD_DEFAULT);
            $stmt_user->execute([$staff_name, $password_hashed, $old_name]);

            $stmt_staff = $pdo->prepare("UPDATE staff SET staff_name = ?, dob = ?, address = ?, email = ?, phone_number = ?, major_subject = ?$photoUpdateSql WHERE id = ?");
            $stmt_staff->execute($params);

            $pdo->commit();
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Staff updated successfully.'];
            header("Location: view_staff.php");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = 'Update failed: ' . $e->getMessage();
            header("Location: staff_form.php?id=$id");
            exit();
        }
        break;

    case 'delete_staff':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can perform this action.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'active';
        try {
            $pdo->beginTransaction();
            $stmt_name = $pdo->prepare("SELECT staff_name FROM staff WHERE id = ?");
            $stmt_name->execute([$id]);
            $staff_name = $stmt_name->fetchColumn();

            if ($staff_name) {
                $stmt = $pdo->prepare("UPDATE staff SET status = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $stmt_user = $pdo->prepare("UPDATE users SET is_active = 0 WHERE username = ? AND role = 'Staff'");
                $stmt_user->execute([$staff_name]);
                $pdo->commit();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Staff moved to Inactive.'];
            } else {
                throw new Exception("Staff not found.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Action failed: ' . $e->getMessage()];
        }
        header("Location: view_staff.php?filter=" . urlencode($ref));
        exit();
        break;

    case 'restore_staff':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can perform this action.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'inactive';
        try {
            $pdo->beginTransaction();
            $stmt_name = $pdo->prepare("SELECT staff_name FROM staff WHERE id = ?");
            $stmt_name->execute([$id]);
            $staff_name = $stmt_name->fetchColumn();

            if ($staff_name) {
                $stmt = $pdo->prepare("UPDATE staff SET status = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $stmt_user = $pdo->prepare("UPDATE users SET is_active = 1 WHERE username = ? AND role = 'Staff'");
                $stmt_user->execute([$staff_name]);
                $pdo->commit();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Staff restored to Active.'];
            } else {
                throw new Exception("Staff not found.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Action failed: ' . $e->getMessage()];
        }
        header("Location: view_staff.php?filter=" . urlencode($ref));
        exit();
        break;

    // ==========================================
    // STUDENT MANAGEMENT
    // ==========================================
    case 'add_student':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can add students.'];
            header("Location: dashboard.php");
            exit();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: dashboard.php");
            exit();
        }

        $student_name  = trim($_POST['student_name'] ?? '');
        $student_id    = trim($_POST['student_id'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $gender        = trim($_POST['gender'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $mobile        = trim($_POST['mobile'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $grade         = trim($_POST['grade'] ?? '');

        // File upload
        $photoPath = 'uploads/default_student.png';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['photo']['name']);
            $extension = strtolower($fileInfo['extension']);
            if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                $newFileName = uniqid('stu_') . '.' . $extension;
                $targetFile = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                    $photoPath = 'uploads/' . $newFileName;
                }
            }
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO students (student_id, student_name, date_of_birth, gender, email, mobile, address, grade, role, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Student', ?)");
            $stmt->execute([$student_id, $student_name, $date_of_birth, $gender, $email, $mobile, $address, $grade, $photoPath]);
            $new_db_id = $pdo->lastInsertId();
            
            // Sync to users table — status is 'Approved' immediately since admin adds students directly
            $password_hashed = password_hash(date('d-m-Y', strtotime($date_of_birth)), PASSWORD_DEFAULT);
            $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, is_active, status, email) VALUES (?, ?, 'Student', 1, 'Approved', ?)");
            $stmt_user->execute([$student_id, $password_hashed, $email]);

            // Assign Fees automatically
            $stmt_fee = $pdo->prepare("SELECT id, total_fee FROM fee_structures WHERE grade = ? AND status = 'Active'");
            $stmt_fee->execute([$grade]);
            $fee_struct = $stmt_fee->fetch(PDO::FETCH_ASSOC);
            if ($fee_struct) {
                $stmt_ins_fee = $pdo->prepare("INSERT INTO student_fees (student_id, fee_structure_id, total_fee, pending_amount) VALUES (?, ?, ?, ?)");
                $stmt_ins_fee->execute([$new_db_id, $fee_struct['id'], $fee_struct['total_fee'], $fee_struct['total_fee']]);
            }

            $pdo->commit();
            
            if (!empty($email)) {
                $subject = "Welcome to Smart Campus Matric Higher Secondary School";
                $body = "<h3>Welcome to Smart Campus</h3>
                         <p>Dear $student_name,</p>
                         <p>Student registration was successful.</p>
                         <p><strong>Student ID:</strong> $student_id<br>
                         <strong>Grade:</strong> $grade<br>
                         <strong>Username:</strong> $student_id<br>
                         <strong>Login Password:</strong> " . date('d-m-Y', strtotime($date_of_birth)) . "</p>
                         <p>Contact the school for more details.</p>";
                $mailer->sendEmail($email, $subject, $body);
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Student registered successfully.'];
            header("Location: view_students.php");
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Registration failed: ' . $e->getMessage()];
            header("Location: student_form.php");
        }
        exit();
        break;

    case 'edit_student':
        if ($userRole !== 'Admin' && $userRole !== 'Staff') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Insufficient permissions.'];
            header("Location: dashboard.php");
            exit();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: dashboard.php");
            exit();
        }

        $id            = intval($_POST['id'] ?? 0);
        $student_name  = trim($_POST['student_name'] ?? '');
        $student_id    = trim($_POST['student_id'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $gender        = trim($_POST['gender'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $mobile        = trim($_POST['mobile'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $grade         = trim($_POST['grade'] ?? '');

        try {
            $pdo->beginTransaction();

            // Fetch current grade and student_id before updating
            $stmt_old = $pdo->prepare("SELECT grade, student_id FROM students WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
            $old_grade = $old_data['grade'] ?? null;
            $old_student_id = $old_data['student_id'] ?? null;

            // Check if photo was uploaded
            $photoUpdateSql = "";
            $params = [$student_id, $student_name, $date_of_birth, $gender, $email, $mobile, $address, $grade];
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileInfo = pathinfo($_FILES['photo']['name']);
                $extension = strtolower($fileInfo['extension']);
                if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                    $newFileName = uniqid('stu_') . '.' . $extension;
                    $targetFile = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                        $photoPath = 'uploads/' . $newFileName;
                        $photoUpdateSql = ", photo = ?";
                        $params[] = $photoPath;
                    }
                }
            }
            
            $params[] = $id;

            $stmt = $pdo->prepare("UPDATE students SET student_id = ?, student_name = ?, date_of_birth = ?, gender = ?, email = ?, mobile = ?, address = ?, grade = ?$photoUpdateSql WHERE id = ?");
            $stmt->execute($params);
            
            // Sync users table
            if ($old_student_id) {
                $password_hashed = password_hash(date('d-m-Y', strtotime($date_of_birth)), PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("UPDATE users SET username = ?, password = ?, email = ? WHERE username = ? AND role = 'Student'");
                $stmt_user->execute([$student_id, $password_hashed, $email, $old_student_id]);
            }
            
            // If grade changed, delete assignments and marks
            if ($old_grade && $old_grade !== $grade) {
                $stmt_unassign = $pdo->prepare("DELETE FROM class_students WHERE student_id = ?");
                $stmt_unassign->execute([$id]);

                $stmt_del_marks = $pdo->prepare("DELETE FROM student_marks WHERE student_id = ?");
                $stmt_del_marks->execute([$id]);

                // Update Fees for new grade
                $stmt_fee = $pdo->prepare("SELECT id, total_fee FROM fee_structures WHERE grade = ? AND status = 'Active'");
                $stmt_fee->execute([$grade]);
                $fee_struct = $stmt_fee->fetch(PDO::FETCH_ASSOC);
                if ($fee_struct) {
                    $stmt_upd_fee = $pdo->prepare("UPDATE student_fees SET fee_structure_id = ?, total_fee = ?, pending_amount = ? - paid_amount WHERE student_id = ?");
                    $stmt_upd_fee->execute([$fee_struct['id'], $fee_struct['total_fee'], $fee_struct['total_fee'], $id]);
                }
            }

            $pdo->commit();
            
            // Send profile update email
            if (!empty($email)) {
                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                $subject = "Profile Updated Successfully";
                $body = "<h3>Profile Update Confirmation</h3>
                         <p>Dear $student_name,</p>
                         <p>Your student profile has been successfully updated in our system.</p>
                         <p>If you did not request these changes, please contact the administration immediately.</p>";
                $mailer->sendEmail($email, $subject, $body);
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Student updated successfully.'];
            header("Location: view_students.php");
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Update failed: ' . $e->getMessage()];
            header("Location: student_form.php?id=$id");
        }
        exit();
        break;

    case 'delete':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied. Only administrators can delete students.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'active';
        try {
            $stmt = $pdo->prepare("UPDATE students SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            // Send Email for Status Update
            require_once __DIR__ . '/includes/Mailer.php';
            $mailer = new Mailer($pdo);
            $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id = ?");
            $stmt_stu->execute([$id]);
            $stu = $stmt_stu->fetch();
            if ($stu && !empty($stu['email'])) {
                $subject = "Profile Status Update: Inactive";
                $body = "<h3>Profile Status Update</h3>
                         <p>Dear {$stu['student_name']},</p>
                         <p>Your student profile has been marked as <strong>Inactive</strong>.</p>
                         <p>If you believe this is an error or if you have graduated, please contact the administration.</p>";
                $mailer->sendEmail($stu['email'], $subject, $body);
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Student moved to Inactive.'];
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Action failed: ' . $e->getMessage()];
        }
        header("Location: view_students.php?filter=" . urlencode($ref));
        exit();
        break;

    case 'restore':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'inactive';
        try {
            $stmt = $pdo->prepare("UPDATE students SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            
            // Send Email for Status Update
            require_once __DIR__ . '/includes/Mailer.php';
            $mailer = new Mailer($pdo);
            $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id = ?");
            $stmt_stu->execute([$id]);
            $stu = $stmt_stu->fetch();
            if ($stu && !empty($stu['email'])) {
                $subject = "Profile Status Update: Active";
                $body = "<h3>Profile Status Update</h3>
                         <p>Dear {$stu['student_name']},</p>
                         <p>Your student profile has been restored and is now <strong>Active</strong>.</p>
                         <p>You may now log in to the student portal.</p>";
                $mailer->sendEmail($stu['email'], $subject, $body);
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Student restored.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Restore failed: ' . $e->getMessage()];
        }
        header("Location: view_students.php?filter=" . urlencode($ref));
        exit();
        break;

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: register.php");
            exit();
        }
        
        $username = trim($_POST['username'] ?? '');
        $dob      = trim($_POST['dob'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $role     = trim($_POST['role'] ?? '');

        if (empty($username) || empty($dob) || empty($address) || empty($role)) {
            $_SESSION['error'] = 'All fields are required.';
            $_SESSION['form_data'] = $_POST;
            header("Location: register.php");
            exit();
        }

        if ($role !== 'Admin' && $role !== 'Staff') {
            $_SESSION['error'] = 'Invalid role selected.';
            header("Location: register.php");
            exit();
        }

        try {
            // Check if username is unique
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = 'Username already exists.';
                $_SESSION['form_data'] = $_POST;
                header("Location: register.php");
                exit();
            }

            // Generate unique ID
            $is_unique = false;
            $unique_id = '';
            while (!$is_unique) {
                $rand_num = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                if ($role === 'Admin') {
                    $unique_id = "ADM-{$rand_num}";
                    $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE admin_id = ?");
                } else {
                    $unique_id = "STF-{$rand_num}";
                    $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE staff_id = ?");
                }
                $stmt_chk->execute([$unique_id]);
                if ($stmt_chk->fetchColumn() == 0) $is_unique = true;
            }

            // Password is DOB in DD-MM-YYYY
            $password_raw = date('d-m-Y', strtotime($dob));
            $password_hashed = password_hash($password_raw, PASSWORD_DEFAULT);

            $pdo->beginTransaction();

            $photoPath = 'uploads/default_avatar.png';
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/profile_photos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $prefix = $role === 'Admin' ? 'adm_' : 'stf_';
                    $newName = uniqid($prefix) . '.' . $ext;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $newName)) {
                        $photoPath = 'uploads/profile_photos/' . $newName;
                    }
                }
            }

            // Step 1: Insert into role table
            if ($role === 'Admin') {
                $stmt_admin = $pdo->prepare("INSERT INTO admins (admin_id, admin_name, dob, address, photo, status) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt_admin->execute([$unique_id, $username, $dob, $address, $photoPath]);
            } else {
                $stmt_staff = $pdo->prepare("INSERT INTO staff (staff_id, staff_name, dob, address, photo, status) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt_staff->execute([$unique_id, $username, $dob, $address, $photoPath]);
            }

            // Step 2: Insert into users table
            $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role, is_active, email) VALUES (?, ?, 'Student', 1, ?)");
            $stmt_user->execute([$student_id, $password_hashed, $email]);

            $pdo->commit();
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Registration successful. You can now login.'];
            header("Location: login.php");
            exit();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = 'Registration failed: ' . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            header("Location: register.php");
            exit();
        }
        break;

    // ==========================================
    // CLASSES MANAGEMENT
    // ==========================================
    case 'add_class':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $grade            = trim($_POST['grade'] ?? '');
        $section          = trim($_POST['section'] ?? '');
        $description      = trim($_POST['description'] ?? '');
        $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : null;

        if (empty($grade) || empty($section)) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Grade and Section are required.'];
            header("Location: class_form.php");
            exit();
        }
        $class_name = $grade . '-' . $section;
        try {
            $stmt = $pdo->prepare("INSERT INTO classes (class_name, grade, section, description, class_teacher_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$class_name, $grade, $section, $description, $class_teacher_id]);
            
            // Class Teacher Email
            if ($class_teacher_id) {
                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                $stmt_staff = $pdo->prepare("SELECT staff_name, email FROM staff WHERE id = ?");
                $stmt_staff->execute([$class_teacher_id]);
                $staff = $stmt_staff->fetch();
                if ($staff && !empty($staff['email'])) {
                    $subject = "Class Teacher Assignment";
                    $body = "<h3>Class Teacher Assignment</h3>
                             <p>Dear {$staff['staff_name']},</p>
                             <p>You have been assigned as the Class Teacher for <strong>{$class_name}</strong>.</p>
                             <p>Please check your dashboard for further details.</p>";
                    $mailer->sendEmail($staff['email'], $subject, $body);
                }
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Class added successfully.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Failed to add class: ' . $e->getMessage()];
        }
        header("Location: view_classes.php");
        exit();
        break;

    case 'edit_class':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id               = intval($_POST['id'] ?? 0);
        $grade            = trim($_POST['grade'] ?? '');
        $section          = trim($_POST['section'] ?? '');
        $description      = trim($_POST['description'] ?? '');
        $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : null;

        if (empty($id) || empty($grade) || empty($section)) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Grade and Section are required.'];
            header("Location: class_form.php?id=" . $id);
            exit();
        }
        $class_name = $grade . '-' . $section;
        $stmt_old = $pdo->prepare("SELECT class_teacher_id FROM classes WHERE id = ?");
        $stmt_old->execute([$id]);
        $old_ct = $stmt_old->fetchColumn();
        
        try {
            $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, grade = ?, section = ?, description = ?, class_teacher_id = ? WHERE id = ?");
            $stmt->execute([$class_name, $grade, $section, $description, $class_teacher_id, $id]);
            
            // Class Teacher Email
            if ($class_teacher_id && $class_teacher_id != $old_ct) {
                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                $stmt_staff = $pdo->prepare("SELECT staff_name, email FROM staff WHERE id = ?");
                $stmt_staff->execute([$class_teacher_id]);
                $staff = $stmt_staff->fetch();
                if ($staff && !empty($staff['email'])) {
                    $subject = "Class Teacher Assignment";
                    $body = "<h3>Class Teacher Assignment</h3>
                             <p>Dear {$staff['staff_name']},</p>
                             <p>You have been assigned as the Class Teacher for <strong>{$class_name}</strong>.</p>
                             <p>Please check your dashboard for further details.</p>";
                    $mailer->sendEmail($staff['email'], $subject, $body);
                }
            }
            
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Class updated successfully.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Failed to update class: ' . $e->getMessage()];
        }
        header("Location: view_classes.php");
        exit();
        break;

    case 'delete_class':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'active';
        try {
            $stmt = $pdo->prepare("UPDATE classes SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Class deactivated.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Deletion failed: ' . $e->getMessage()];
        }
        header("Location: view_classes.php?filter=" . urlencode($ref));
        exit();
        break;

    case 'restore_class':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'inactive';
        try {
            $stmt = $pdo->prepare("UPDATE classes SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Class restored.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Restore failed: ' . $e->getMessage()];
        }
        header("Location: view_classes.php?filter=" . urlencode($ref));
        exit();
        break;

    // ==========================================
    // CLASS STUDENT MANAGEMENT
    // ==========================================
    case 'remove_student_from_class':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $student_id = intval($_GET['student_id'] ?? 0);
        $class_id   = intval($_GET['class_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM class_students WHERE student_id = ? AND class_id = ?");
            $stmt->execute([$student_id, $class_id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Student removed from class successfully.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Removal failed: ' . $e->getMessage()];
        }
        header("Location: class_details.php?id=" . $class_id);
        exit();
        break;

    case 'assign_single_student':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $class_id = intval($_POST['class_id'] ?? 0);
            $student_id = intval($_POST['student_id'] ?? 0);
            
            try {
                // Ensure the student isn't already assigned
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM class_students WHERE student_id = ?");
                $stmt_check->execute([$student_id]);
                if ($stmt_check->fetchColumn() == 0) {
                    $stmt_ins = $pdo->prepare("INSERT INTO class_students (class_id, student_id) VALUES (?, ?)");
                    $stmt_ins->execute([$class_id, $student_id]);
                    // Email Notification
                    require_once __DIR__ . '/includes/Mailer.php';
                    $mailer = new Mailer($pdo);
                    $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id = ?");
                    $stmt_stu->execute([$student_id]);
                    $stu = $stmt_stu->fetch();
                    $stmt_cls = $pdo->prepare("SELECT class_name, grade, section FROM classes WHERE id = ?");
                    $stmt_cls->execute([$class_id]);
                    $cls = $stmt_cls->fetch();
                    if ($stu && !empty($stu['email']) && $cls) {
                        $stmt_fee = $pdo->prepare("SELECT total_fee FROM fee_structures WHERE grade = ? AND status = 'Active'");
                        $stmt_fee->execute([$cls['grade']]);
                        $fee_struct = $stmt_fee->fetch();
                        $fee_info = $fee_struct ? "<p>Your total allotted fee is: <strong>₹{$fee_struct['total_fee']}</strong></p>" : "";

                        $subject = "Class Assignment & Fee Allotment";
                        $body = "<h3>Class Assignment Notification</h3>
                                 <p>Dear {$stu['student_name']},</p>
                                 <p>You have been assigned to <strong>{$cls['class_name']}</strong> (Grade: {$cls['grade']}, Section: {$cls['section']}).</p>
                                 <p>Any applicable fee structures for your grade have also been automatically allotted.</p>
                                 {$fee_info}";
                        $mailer->sendEmail($stu['email'], $subject, $body);
                    }
                    $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Student assigned successfully.'];
                } else {
                    $_SESSION['toast'] = ['type' => 'warning', 'msg' => 'Student is already assigned to a class.'];
                }
            } catch (\PDOException $e) {
                $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Assignment failed: ' . $e->getMessage()];
            }
            header("Location: assign_students.php?class_id=" . $class_id);
            exit();
        }
        break;


    case 'transfer_student':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $student_id = intval($_POST['student_id'] ?? 0);
            $from_class = intval($_POST['from_class'] ?? 0);
            $to_class   = intval($_POST['to_class'] ?? 0);
            
            if (!$to_class) {
                $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Please select a destination class.'];
                header("Location: transfer_student.php?student_id=$student_id&from_class=$from_class");
                exit();
            }

            try {
                $pdo->beginTransaction();
                // Remove from old class
                $stmt_del = $pdo->prepare("DELETE FROM class_students WHERE student_id = ? AND class_id = ?");
                $stmt_del->execute([$student_id, $from_class]);
                
                // Remove marks associated with subjects from the old class
                $stmt_del_old_marks = $pdo->prepare("DELETE sm FROM student_marks sm JOIN subjects s ON sm.subject_id = s.id WHERE sm.student_id = ? AND s.class_id = ?");
                $stmt_del_old_marks->execute([$student_id, $from_class]);
                
                // Add to new class (ignore if already there)
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM class_students WHERE student_id = ? AND class_id = ?");
                $stmt_check->execute([$student_id, $to_class]);
                if ($stmt_check->fetchColumn() == 0) {
                    $stmt_ins = $pdo->prepare("INSERT INTO class_students (student_id, class_id) VALUES (?, ?)");
                    $stmt_ins->execute([$student_id, $to_class]);
                }
                
                // Update the student's grade to match the new class
                $stmt_new_class = $pdo->prepare("SELECT grade FROM classes WHERE id = ?");
                $stmt_new_class->execute([$to_class]);
                $new_grade = $stmt_new_class->fetchColumn();
                
                if ($new_grade) {
                    $stmt_upd_grade = $pdo->prepare("UPDATE students SET grade = ? WHERE id = ?");
                    $stmt_upd_grade->execute([$new_grade, $student_id]);
                    
                    // Update Fees for new grade
                    $stmt_fee = $pdo->prepare("SELECT id, total_fee FROM fee_structures WHERE grade = ? AND status = 'Active'");
                    $stmt_fee->execute([$new_grade]);
                    $fee_struct = $stmt_fee->fetch(PDO::FETCH_ASSOC);
                    if ($fee_struct) {
                        $stmt_upd_fee = $pdo->prepare("UPDATE student_fees SET fee_structure_id = ?, total_fee = ?, pending_amount = ? - paid_amount WHERE student_id = ?");
                        $stmt_upd_fee->execute([$fee_struct['id'], $fee_struct['total_fee'], $fee_struct['total_fee'], $student_id]);
                    }
                }
                
                $pdo->commit();
                // Email Notification
                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                $stmt_stu = $pdo->prepare("SELECT student_name, email FROM students WHERE id = ?");
                $stmt_stu->execute([$student_id]);
                $stu = $stmt_stu->fetch();
                $stmt_cls = $pdo->prepare("SELECT class_name, grade, section FROM classes WHERE id = ?");
                $stmt_cls->execute([$to_class]);
                $cls = $stmt_cls->fetch();
                if ($stu && !empty($stu['email']) && $cls) {
                    $stmt_fee = $pdo->prepare("SELECT total_fee FROM fee_structures WHERE grade = ? AND status = 'Active'");
                    $stmt_fee->execute([$cls['grade']]);
                    $fee_struct = $stmt_fee->fetch();
                    $fee_info = $fee_struct ? "<p>Your total allotted fee is: <strong>₹{$fee_struct['total_fee']}</strong></p>" : "";

                    $subject = "Class Transfer & Fee Allotment Update";
                    $body = "<h3>Class Transfer Notification</h3>
                             <p>Dear {$stu['student_name']},</p>
                             <p>You have been successfully transferred to <strong>{$cls['class_name']}</strong> (Grade: {$cls['grade']}, Section: {$cls['section']}).</p>
                             <p>Any applicable fee structures for your new grade have been automatically allotted to your account.</p>
                             {$fee_info}";
                    $mailer->sendEmail($stu['email'], $subject, $body);
                }
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Student transferred successfully.'];
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Transfer failed: ' . $e->getMessage()];
            }
            header("Location: class_details.php?id=" . $from_class);
            exit();
        }
        break;

    // ==========================================
    // SUBJECTS MANAGEMENT
    // ==========================================
    case 'add_subject':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $subject_name = trim($_POST['subject_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $class_id = intval($_POST['class_id'] ?? 0);
        if (empty($subject_name) || empty($class_id)) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'All fields are required.'];
            header("Location: subject_form.php");
            exit();
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, description, class_id) VALUES (?, ?, ?)");
            $stmt->execute([$subject_name, $description, $class_id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Subject added successfully.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Failed to add subject: ' . $e->getMessage()];
        }
        header("Location: class_details.php?id=" . $class_id);
        exit();
        break;

    case 'edit_subject':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id           = intval($_POST['id'] ?? 0);
        $subject_name = trim($_POST['subject_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $class_id     = intval($_POST['class_id'] ?? 0);
        if (empty($id) || empty($subject_name) || empty($class_id)) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'All fields are required.'];
            header("Location: subject_form.php?id=" . $id);
            exit();
        }
        try {
            $stmt = $pdo->prepare("UPDATE subjects SET subject_name = ?, description = ?, class_id = ? WHERE id = ?");
            $stmt->execute([$subject_name, $description, $class_id, $id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Subject updated successfully.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Failed to update subject: ' . $e->getMessage()];
        }
        header("Location: class_details.php?id=" . $class_id);
        exit();
        break;

    case 'delete_subject':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'active';
        try {
            $stmt = $pdo->prepare("UPDATE subjects SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Subject deactivated.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Deletion failed: ' . $e->getMessage()];
        }
        header("Location: view_subjects.php?filter=" . urlencode($ref));
        exit();
        break;

    case 'restore_subject':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $ref = $_GET['ref'] ?? 'inactive';
        try {
            $stmt = $pdo->prepare("UPDATE subjects SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Subject restored.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Restore failed: ' . $e->getMessage()];
        }
        header("Location: view_subjects.php?filter=" . urlencode($ref));
        exit();
        break;

    case 'delete_subject_class':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $id = intval($_GET['id'] ?? 0);
        $class_id = intval($_GET['class_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE subjects SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Subject deactivated.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Deletion failed: ' . $e->getMessage()];
        }
        header("Location: class_details.php?id=" . $class_id);
        exit();
        break;

    case 'update_institution':
        if ($userRole !== 'Admin') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }
        $school_name = trim($_POST['school_name'] ?? '');
        $institution_code = trim($_POST['institution_code'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $principal = trim($_POST['principal'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $total_students = intval($_POST['total_students'] ?? 0);
        $total_staff = intval($_POST['total_staff'] ?? 0);
        $address = trim($_POST['address'] ?? '');

        if (empty($school_name) || empty($email) || empty($phone)) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Required fields cannot be empty.'];
            header("Location: institution_profile.php");
            exit();
        }

        try {
            $stmt = $pdo->prepare("UPDATE institution_profile SET 
                school_name = ?, institution_code = ?, email = ?, phone = ?, 
                principal = ?, website = ?, total_students = ?, total_staff = ?, address = ? 
                WHERE id = 1");
            $stmt->execute([$school_name, $institution_code, $email, $phone, $principal, $website, $total_students, $total_staff, $address]);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Institution profile updated successfully.'];
        } catch (\PDOException $e) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Failed to update institution profile: ' . $e->getMessage()];
        }
        header("Location: institution_profile.php");
        exit();
        break;

    default:
        header("Location: dashboard.php");
        exit();
        break;
}
?>