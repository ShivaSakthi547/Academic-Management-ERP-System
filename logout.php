<?php
// logout.php
session_start();

// Destroy the session
session_unset();
session_destroy();

// Start a temporary session just to pass the toast message
session_start();
$_SESSION['toast'] = [
    'type' => 'success',
    'msg' => 'Logged out successfully.'
];

header("Location: login.php");
exit();
?>
