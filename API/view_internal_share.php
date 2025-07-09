<?php
include "sql_conn.php";
include "myfunctions.php";

$current_email = DecryptSessionsandCookies($_SESSION['passed_user_email']);

$account_id = isset($_GET['account_id']) ? $_GET['account_id'] : '';
$vault_type = isset($_GET['type']) ? $_GET['type'] : '';
$share_uuid = isset($_GET['share_uuid']) ? $_GET['share_uuid'] : '';

if (!$current_email || !$account_id || !$vault_type || !$share_uuid) {
    echo json_encode(["success" => false, "error" => "Missing parameters or not authenticated"]);
    exit;
}

// Find the share record, make sure it's internal, valid, not expired/revoked, and user is owner or target
$sql = "SELECT * FROM shared_account
        WHERE share_uuid = ?
        AND shared_type = 'internal'
        AND delete_flag = 0
        AND (expiry_at IS NULL OR expiry_at > NOW())
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
    exit;
}
$stmt->bind_param("s", $share_uuid);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || !$res->num_rows) {
    echo json_encode(["success" => false, "error" => "Invalid or expired share link"]);
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

if ($row['account_id'] !== $account_id || $row['vault_type'] !== $vault_type) {
    echo json_encode(["success" => false, "error" => "Account mismatch"]);
    exit;
}

// Only allow owner or target (recipient) to view the item
if (
    strtolower($current_email) !== strtolower($row['owner_email']) &&
    strtolower($current_email) !== strtolower($row['target_email'])
) {
    echo json_encode(["success" => false, "error" => "You are not authorized to view this shared item"]);
    exit;
}

$data = [
    "vault_type"    => $vault_type,
    "account_id"    => $account_id,
    "share_uuid"    => $share_uuid,
    "account_name"  => "N/A",
    "owner_email"   => $row['owner_email'],
    "target_email"  => $row['target_email'],
    "no_expiry"     => $row['no_expiry'],
    "expiry_at"     => $row['expiry_at'],
    "shared_on"     => $row['shared_on']
];

// Fetch fields from the correct table
if ($vault_type === "password") {
    $q = $conn->prepare("SELECT AppName, UserName, Password, Url, Notes, EncryptionKeyId FROM vault WHERE CONVERT(UniqueId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci AND DeleteFlag = 0 LIMIT 1");
    if ($q) {
        $q->bind_param("s", $account_id);
        $q->execute();
        $r = $q->get_result();
        if ($pw = $r->fetch_assoc()) {
            $encryptionKeyId = $pw['EncryptionKeyId'];
            $ownerEmail = $row['owner_email'];
            $encQuery = $conn->prepare("SELECT EncryptionKey FROM encryption WHERE EncryptionKeyVersion = ? AND UserEmailId = ?");
            $encKey = '';
            if ($encQuery) {
                $encQuery->bind_param("ss", $encryptionKeyId, $ownerEmail);
                $encQuery->execute();
                $encResult = $encQuery->get_result();
                if ($encRow = $encResult->fetch_assoc()) {
                    $encKey = $encRow['EncryptionKey'];
                }
                $encQuery->close();
            }

            $decryptedPassword = $encKey ? decryptString($pw["Password"], $encKey) : "Unable to decrypt";

            $data["account_name"] = $pw["AppName"];
            $data["UserName"]     = $pw["UserName"];
            $data["Password"]     = $decryptedPassword;
            $data["Url"]          = $pw["Url"];
            $data["Notes"]        = $pw["Notes"];
        }
        $q->close();
    }
// note (notes)
} else if ($vault_type === "note") {
    $q = $conn->prepare("SELECT Title, Notes FROM notes WHERE CONVERT(UniqueId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci AND DeleteFlag = 0 LIMIT 1");
    if ($q) {
        $q->bind_param("s", $account_id);
        $q->execute();
        $r = $q->get_result();
        if ($note = $r->fetch_assoc()) {
            $data["account_name"] = $note["Title"];
            $data["Title"]        = $note["Title"];
            $data["Notes"]        = $note["Notes"];
        }
        $q->close();
    }
} else if ($vault_type === "contact") {
    $q = $conn->prepare("SELECT FirstName, LastName, Fields, Notes FROM address_book WHERE CONVERT(UniqueId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci AND DeleteFlag = 0 LIMIT 1");
    if ($q) {
        $q->bind_param("s", $account_id);
        $q->execute();
        $r = $q->get_result();
        if ($c = $r->fetch_assoc()) {
            $data["account_name"] = trim($c["FirstName"] . " " . $c["LastName"]);
            $data["FirstName"]    = $c["FirstName"];
            $data["LastName"]     = $c["LastName"];
            $data["Fields"]       = $c["Fields"];
            $data["Notes"]        = $c["Notes"];
        }
        $q->close();
    }
} else if ($vault_type === "card") {
    $q = $conn->prepare("SELECT CardName, Fields, Notes FROM cards WHERE CONVERT(UniqueId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_general_ci AND DeleteFlag = 0 LIMIT 1");
    if ($q) {
        $q->bind_param("s", $account_id);
        $q->execute();
        $r = $q->get_result();
        if ($card = $r->fetch_assoc()) {
            $data["account_name"] = $card["CardName"];
            $data["CardName"]     = $card["CardName"];
            $data["Fields"]       = $card["Fields"];
            $data["Notes"]        = $card["Notes"];
        }
        $q->close();
    }
}

echo json_encode(["success" => true, "data" => $data]);
?>
