<?php
// process_email_queue.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/includes/PHPMailer/src/Exception.php';
require_once __DIR__ . '/includes/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/includes/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/config/db.php';

$queueDir = __DIR__ . '/email_queue/';
if (!is_dir($queueDir)) {
    exit;
}

$files = glob($queueDir . '*.json');
if (empty($files)) {
    exit;
}

$config = require __DIR__ . '/config/mail_config.php';

// Process only up to 5 files per script execution to avoid long-running background processes
$files = array_slice($files, 0, 5);

foreach ($files as $file) {
    if (!file_exists($file)) continue;

    $content = file_get_contents($file);
    $data = json_decode($content, true);
    
    // Rename to .processing to avoid race conditions
    $processingFile = $file . '.processing';
    if (!rename($file, $processingFile)) {
        continue; // Could not rename, maybe another process grabbed it
    }

    if ($data) {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['smtp_user'];
            $mail->Password   = $config['smtp_pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['smtp_port'];

            // Recipients
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($data['to']);

            // Attachments
            if (!empty($data['attachment'])) {
                $attachmentData = base64_decode($data['attachment']);
                $mail->addStringAttachment($attachmentData, $data['attachmentName']);
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $data['subject'];
            $mail->Body    = $data['body'];
            $mail->AltBody = strip_tags($data['body']);

            $mail->send();
            
            // Log success
            try {
                $stmt = $pdo->prepare("UPDATE email_logs SET status = 'Sent', error_message = NULL WHERE recipient_email = ? AND subject = ? AND status = 'Queued' ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$data['to'], $data['subject']]);
            } catch (\PDOException $e) {}

        } catch (Exception $e) {
            // Log failure
            try {
                $stmt = $pdo->prepare("UPDATE email_logs SET status = 'Failed', error_message = ? WHERE recipient_email = ? AND subject = ? AND status = 'Queued' ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$mail->ErrorInfo, $data['to'], $data['subject']]);
            } catch (\PDOException $err) {}
        }
    }
    
    @unlink($processingFile);
}
