<?php   
error_reporting(E_ALL ^ E_WARNING);
error_reporting(E_ERROR | E_PARSE);

// Database connection 
include "sql_conn.php";
include 'myfunctions.php';

header('Content-Type: application/json');

// --- AUTH: Check if user is logged in ---
if (!isset($_SESSION['passed_user_email'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Decrypt session email if necessary
$user_email = DecryptSessionsandCookies($_SESSION['passed_user_email']);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
$offset = ($page - 1) * $per_page;

// Filters
$email = trim($_GET['email'] ?? '');
$action = trim($_GET['action'] ?? '');
$ip = trim($_GET['ip'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

// Only fetch for this user, ignore if filter email is set and is not same as logged-in user
$where = "WHERE user_email_id = ?";
$params = [$user_email];
$types = "s";

if ($action) {
    $where .= " AND action LIKE ?";
    $params[] = "%$action%";
    $types .= "s";
}
if ($ip) {
    $where .= " AND ip_address LIKE ?";
    $params[] = "%$ip%";
    $types .= "s";
}
if ($from) {
    $where .= " AND timestamp >= ?";
    $params[] = $from . " 00:00:00";
    $types .= "s";
}
if ($to) {
    $where .= " AND timestamp <= ?";
    $params[] = $to . " 23:59:59";
    $types .= "s";
}

// Count total rows for pagination
$count_sql = "SELECT COUNT(*) FROM audit_logs $where AND deleteflag=0";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total_rows);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_rows / $per_page);

// Fetch logs
$sql = "SELECT id, user_email_id, action, details, ip_address, user_agent, timestamp 
        FROM audit_logs $where AND deleteflag=0
        ORDER BY timestamp DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$types_with_limits = $types . "ii";
$params_with_limits = $params;
$params_with_limits[] = $per_page;
$params_with_limits[] = $offset;
$stmt->bind_param($types_with_limits, ...$params_with_limits);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'data' => $data,
    'from' => $offset + 1,
    'total_pages' => $total_pages
]);
