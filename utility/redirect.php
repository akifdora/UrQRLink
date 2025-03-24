<?php
require 'config.php';

if (isset($_GET['code'])) {
    $short_code = $_GET['code'];

    $stmt = $conn->prepare("SELECT id, original_url FROM urls WHERE short_code = ?");
    $stmt->execute([$short_code]);
    $url_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($url_data) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM clicks WHERE url_id = ? AND ip_address = ? AND user_agent = ? AND clicked_at >= NOW() - INTERVAL 1 HOUR");
        $stmt->execute([$url_data['id'], $ip_address, $user_agent]);
        $recent_clicks = $stmt->fetchColumn();

        if ($recent_clicks == 0 && !isset($_COOKIE['clicked_'.$short_code])) {
            setcookie('clicked_'.$short_code, '1', time() + 3600);

            $stmt = $conn->prepare("INSERT INTO clicks (url_id, clicked_at, ip_address, user_agent) VALUES (?, NOW(), ?, ?)");
            $stmt->execute([$url_data['id'], $ip_address, $user_agent]);
        }

        header("Location: " . $url_data['original_url']);
        exit;
    } else {
        echo "<div class='alert alert-danger'>Bu link bulunamadı.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Geçersiz istek.</div>";
}
?>
