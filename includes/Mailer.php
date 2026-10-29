<?php
// includes/Mailer.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require PHPMailer manually
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Require TCPDF manually
if (file_exists(__DIR__ . '/tcpdf/tcpdf.php')) {
    require_once __DIR__ . '/tcpdf/tcpdf.php';
}

class Mailer {
    private $pdo;
    private $config;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../config/mail_config.php';
    }

    private function logEmail($recipient, $subject, $status, $errorMsg = null) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO email_logs (recipient_email, subject, status, error_message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$recipient, $subject, $status, $errorMsg]);
        } catch (\PDOException $e) {
            // Silently fail logging to avoid breaking main workflow
            error_log("Email Logging Failed: " . $e->getMessage());
        }
    }

    public function sendEmail($to, $subject, $body, $attachment = null, $attachmentName = 'Document.pdf') {
        if (empty($to)) return false;

        $login_link = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/student_registration/login.php";
        if (strpos($body, 'log in to the portal') === false && strpos($body, 'login to the portal') === false) {
            $body .= "<hr><p>Please <a href='{$login_link}'>log in to the portal</a> for more details.</p>";
        }

        $queueDir = __DIR__ . '/../email_queue/';
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0777, true);
        }

        $mailData = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'attachment' => $attachment !== null ? base64_encode($attachment) : null,
            'attachmentName' => $attachmentName
        ];

        $filename = $queueDir . uniqid(time() . '_') . '.json';
        if (file_put_contents($filename, json_encode($mailData))) {
            $this->logEmail($to, $subject, 'Queued');
            return true;
        } else {
            $this->logEmail($to, $subject, 'Failed', 'Failed to write to queue');
            return false;
        }
    }

    public function sendNotificationEmail($recipient, $subject, $message, $senderName) {
        $body = "
            <h3>School Notification</h3>
            <p><strong>From:</strong> $senderName</p>
            <p>$message</p>
            <hr>
            <p>Please log in to the portal for more details.</p>
        ";
        return $this->sendEmail($recipient, $subject, $body);
    }

    // PDF generation helper using TCPDF
    public function generatePDF($htmlContent) {
        if (!class_exists('TCPDF')) {
            return null;
        }
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Smart Campus');
        $pdf->SetTitle('Document');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->writeHTML($htmlContent, true, false, true, false, '');
        return $pdf->Output('document.pdf', 'S'); // Return as string
    }

    public function sendReportCardEmail($to, $studentName, $className, $percentage, $rank, $htmlContentForPdf) {
        $subject = 'Report Card Published';
        $body = "
            <h3>Report Card Published</h3>
            <p>Dear $studentName,</p>
            <p>The report card for class $className has been generated.</p>
            <p><strong>Percentage:</strong> $percentage%</p>
            <p><strong>Rank:</strong> $rank</p>
            <p>Please find the detailed report card attached.</p>
        ";
        $pdfString = $this->generatePDF($htmlContentForPdf);
        return $this->sendEmail($to, $subject, $body, $pdfString, "Report_Card_{$studentName}.pdf");
    }

    // More specialized functions can be added here
}
