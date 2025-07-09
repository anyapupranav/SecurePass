<?php
include "sql_conn.php";
include 'myfunctions.php';

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Collect data from POST
$action = isset($_POST['action']) ? trim($_POST['action']) : null;
$details = isset($_POST['details']) ? trim($_POST['details']) : null;

// Basic validation
if (!$action) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing action"]);
    exit;
}

// Get user ID from session or set null for anonymous
$userId = DecryptSessionsandCookies(isset($_SESSION['passed_user_email']) ? $_SESSION['passed_user_email'] : null);
$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

try {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_email_id, action, details, ip_address, user_agent) 
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $userId, $action, $details, $ip, $userAgent);
    $stmt->execute();

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to log event", "error" => $e->getMessage()]);
}
?>
