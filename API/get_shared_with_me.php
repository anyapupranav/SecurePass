<?php
include "sql_conn.php"; 
include "myfunctions.php"; 

$target_email = DecryptSessionsandCookies($_SESSION['passed_user_email']);

if (!$target_email) {
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

// Fetch shared items for user (not deleted, not expired)
$sql = "SELECT * FROM shared_account
        WHERE CONVERT(target_email USING utf8mb4) COLLATE utf8mb4_general_ci = 
              CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci
        AND delete_flag = 0
        AND (expiry_at IS NULL OR expiry_at > NOW())
        ORDER BY shared_on DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "success" => false,
        "error" => "SQL Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $target_email);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $vault_type = $row['vault_type'];
    $account_id = $row['account_id'];
    $account_name = "N/A";

    if ($vault_type === "password") {
        $q = $conn->prepare("SELECT AppName FROM vault WHERE CONVERT(UniqueId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1");
        if ($q) {
            $q->bind_param("s", $account_id);
            $q->execute();
            $res = $q->get_result();
            if ($row2 = $res->fetch_assoc()) $account_name = $row2['AppName'];
            $q->close();
        }
    } else if ($vault_type === "note") {
        $q = $conn->prepare("SELECT Title FROM notes WHERE CONVERT(UniqueId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1");
        if ($q) {
            $q->bind_param("s", $account_id);
            $q->execute();
            $res = $q->get_result();
            if ($row2 = $res->fetch_assoc()) $account_name = $row2['Title'];
            $q->close();
        }
    } else if ($vault_type === "contact") {
        $q = $conn->prepare("SELECT CONCAT(FirstName, ' ', LastName) as name FROM address_book WHERE CONVERT(UniqueId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1");
        if ($q) {
            $q->bind_param("s", $account_id);
            $q->execute();
            $res = $q->get_result();
            if ($row2 = $res->fetch_assoc()) $account_name = trim($row2['name']);
            $q->close();
        }
    } else if ($vault_type === "card") {
        $q = $conn->prepare("SELECT CardName FROM cards WHERE CONVERT(UniqueId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci LIMIT 1");
        if ($q) {
            $q->bind_param("s", $account_id);
            $q->execute();
            $res = $q->get_result();
            if ($row2 = $res->fetch_assoc()) $account_name = $row2['CardName'];
            $q->close();
        }
    }

    $data[] = [
        "id" => $row['id'],
        "vault_type" => $vault_type,
        "account_name" => $account_name,
        "account_id" => $row['account_id'],
        "owner_email" => $row['owner_email'],
        "shared_type" => $row['shared_type'],
        "share_uuid" => $row['share_uuid'],
        "access" => "View",
        "shared_on" => $row['shared_on']
    ];
}
echo json_encode(["success" => true, "data" => $data]);
?>
