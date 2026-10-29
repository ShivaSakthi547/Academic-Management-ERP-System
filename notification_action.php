<?php
// notification_action.php
session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax'])) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header("Location: login.php");
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

switch ($action) {
    case 'send':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: dashboard.php");
            exit();
        }

        if ($userRole !== 'Admin' && $userRole !== 'Staff') {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Access denied.'];
            header("Location: dashboard.php");
            exit();
        }

        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target_students = $_POST['target_students'] ?? [];
        $target_staff = $_POST['target_staff'] ?? [];
        $expiry_type = $_POST['expiry_type'] ?? 'no_expiry';
        $expiry_date = null;

        if ($expiry_type === 'add_expiry' && !empty($_POST['expiry_date'])) {
            $expiry_date = $_POST['expiry_date'] . ' 23:59:59'; // End of the selected day
        }

        if (empty($title) || empty($message)) {
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Title and message are required.'];
            header("Location: send_notification.php");
            exit();
        }

        // Determine target_type for recording
        $target_type = 'custom';
        if (in_array('all', $target_students) && in_array('all', $target_staff)) {
            $target_type = 'all';
        } elseif (in_array('all', $target_students)) {
            $target_type = 'all_students';
        } elseif (in_array('all', $target_staff)) {
            $target_type = 'all_staff';
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO notifications (title, sender_role, sender_id, message, target_type, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $userRole, $userId, $message, $target_type, $expiry_date]);
            $notification_id = $pdo->lastInsertId();

            // Collect all target user IDs
            $recipient_ids = [];

            // Students
            if (in_array('all', $target_students)) {
                $stmt_students = $pdo->query("SELECT id FROM users WHERE role = 'Student' AND is_active = 1");
                while ($row = $stmt_students->fetch()) {
                    $recipient_ids[$row['id']] = 'Student';
                }
            } else {
                foreach ($target_students as $sid) {
                    if ($sid !== 'none' && !empty($sid)) {
                        $recipient_ids[$sid] = 'Student';
                    }
                }
            }

            // Staff (Admin only)
            if ($userRole === 'Admin') {
                if (in_array('all', $target_staff)) {
                    $stmt_staff = $pdo->query("SELECT id FROM users WHERE role = 'Staff' AND is_active = 1");
                    while ($row = $stmt_staff->fetch()) {
                        $recipient_ids[$row['id']] = 'Staff';
                    }
                } else {
                    foreach ($target_staff as $sid) {
                        if ($sid !== 'none' && !empty($sid)) {
                            $recipient_ids[$sid] = 'Staff';
                        }
                    }
                }
            }

            // Insert recipients
            if (!empty($recipient_ids)) {
                $insertQuery = "INSERT INTO notification_recipients (notification_id, receiver_role, receiver_id) VALUES ";
                $insertData = [];
                $placeholders = [];
                foreach ($recipient_ids as $r_id => $r_role) {
                    $placeholders[] = "(?, ?, ?)";
                    $insertData[] = $notification_id;
                    $insertData[] = $r_role;
                    $insertData[] = $r_id;
                }
                $insertQuery .= implode(', ', $placeholders);
                $stmt_recipients = $pdo->prepare($insertQuery);
                $stmt_recipients->execute($insertData);
                
                // Send Emails
                require_once __DIR__ . '/includes/Mailer.php';
                $mailer = new Mailer($pdo);
                
                $ids = array_keys($recipient_ids);
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $stmt_emails = $pdo->prepare("SELECT email FROM users WHERE id IN ($inQuery) AND email IS NOT NULL AND email != ''");
                $stmt_emails->execute($ids);
                $emails = $stmt_emails->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($emails as $email) {
                    $mailer->sendNotificationEmail($email, $title, $message, $_SESSION['user_name']);
                }
            }

            $pdo->commit();
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Notification sent successfully.'];
            header("Location: send_notification.php");
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['toast'] = ['type' => 'danger', 'msg' => 'Failed to send notification: ' . $e->getMessage()];
            header("Location: send_notification.php");
        }
        exit();
        break;

    case 'fetch_unread':
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->prepare("
                SELECT n.id, n.title, n.sender_role, u.username as sender_name, n.created_at, nr.is_read
                FROM notifications n
                JOIN notification_recipients nr ON n.id = nr.notification_id
                LEFT JOIN users u ON n.sender_id = u.id
                WHERE nr.receiver_id = ? 
                  AND n.is_active = 1 
                  AND (n.expiry_date IS NULL OR n.expiry_date >= NOW())
                ORDER BY n.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get accurate unread count
            $stmt_count = $pdo->prepare("
                SELECT COUNT(*) 
                FROM notifications n
                JOIN notification_recipients nr ON n.id = nr.notification_id
                WHERE nr.receiver_id = ? 
                  AND nr.is_read = 0 
                  AND n.is_active = 1 
                  AND (n.expiry_date IS NULL OR n.expiry_date >= NOW())
            ");
            $stmt_count->execute([$userId]);
            $unreadCount = $stmt_count->fetchColumn();
            
            // Get total count for 'View All' logic
            $stmt_total = $pdo->prepare("
                SELECT COUNT(*) 
                FROM notifications n
                JOIN notification_recipients nr ON n.id = nr.notification_id
                WHERE nr.receiver_id = ? 
                  AND n.is_active = 1 
                  AND (n.expiry_date IS NULL OR n.expiry_date >= NOW())
            ");
            $stmt_total->execute([$userId]);
            $totalCount = $stmt_total->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'count' => $unreadCount,
                'total_count' => $totalCount,
                'notifications' => $notifications // Already limited to 5
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
        break;

    default:
        header("Location: dashboard.php");
        exit();
}
