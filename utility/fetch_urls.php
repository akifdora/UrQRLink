<?php
require 'config.php';
session_start();

header('Content-Type: application/json');

$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
$searchValue = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
$query = "
    SELECT u.short_code, u.original_url, u.qr_code, COUNT(c.id) AS click_count
    FROM urls u
    LEFT JOIN clicks c ON u.id = c.url_id
    WHERE u.user_id = :user_id
";

if (!empty($searchValue)) {
    $query .= " AND u.original_url LIKE :search";
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM urls WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalRecords = $stmt->fetchColumn();

$query .= " GROUP BY u.id LIMIT :start, :length";

$stmt = $conn->prepare($query);
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);

if (!empty($searchValue)) {
    $stmt->bindValue(':search', "%$searchValue%", PDO::PARAM_STR);
}

$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);

$stmt->execute();
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);

$response = [
    "draw" => isset($_GET['draw']) ? (int)$_GET['draw'] : 0,
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => !empty($searchValue) ? count($urls) : $totalRecords,
    "data" => $urls
];

echo json_encode($response);
?>
