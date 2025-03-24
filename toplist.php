<?php
require 'utility/config.php';
session_start();

$user_id = $_SESSION['user_id'];

if (!isset($user_id)) {
    header("Location: index.php");
    exit;
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

switch ($filter) {
    case 'daily':
        $sql = "SELECT u.short_code, u.qr_code, u.user_id, COUNT(c.id) AS click_count, usr.username
                FROM urls u
                LEFT JOIN clicks c ON u.id = c.url_id
                LEFT JOIN users usr ON u.user_id = usr.id
                WHERE c.clicked_at >= NOW() - INTERVAL 1 DAY
                GROUP BY u.id
                ORDER BY click_count DESC";
        break;
    case 'weekly':
        $sql = "SELECT u.short_code, u.qr_code, u.user_id, COUNT(c.id) AS click_count, usr.username
                FROM urls u
                LEFT JOIN clicks c ON u.id = c.url_id
                LEFT JOIN users usr ON u.user_id = usr.id
                WHERE c.clicked_at >= NOW() - INTERVAL 1 WEEK
                GROUP BY u.id
                ORDER BY click_count DESC";
        break;
    case '90days':
        $sql = "SELECT u.short_code, u.qr_code, u.user_id, COUNT(c.id) AS click_count, usr.username
                FROM urls u
                LEFT JOIN clicks c ON u.id = c.url_id
                LEFT JOIN users usr ON u.user_id = usr.id
                WHERE c.clicked_at >= NOW() - INTERVAL 90 DAY
                GROUP BY u.id
                ORDER BY click_count DESC";
        break;
    case 'all':
    default:
        $sql = "SELECT u.short_code, u.qr_code, u.user_id, COUNT(c.id) AS click_count, usr.username
                FROM urls u
                LEFT JOIN clicks c ON u.id = c.url_id
                LEFT JOIN users usr ON u.user_id = usr.id
                GROUP BY u.id
                ORDER BY click_count DESC";
        break;
}

$stmt = $conn->prepare($sql);
$stmt->execute();
$urls = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Popüler Linkler</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<?php include 'utility/navbar.php'; ?>

<div class="container mt-5">
    <div class="card shadow-sm p-4">
        <h2 class="text-center">Popüler Linkler</h2>
        <div class="text-center mb-4">
            <a href="?filter=daily" class="btn btn-primary">Günlük</a>
            <a href="?filter=weekly" class="btn btn-primary">Haftalık</a>
            <a href="?filter=90days" class="btn btn-primary">90 Günlük</a>
            <a href="?filter=all" class="btn btn-primary">Tüm Zamanlar</a>
        </div>
        <table id="urlTable" class="table table-striped">
            <thead>
                <tr>
                    <th>Sıra</th>
                    <th>URL Sahibi</th>
                    <th>Kısa URL</th>
                    <th>QR Kod</th>
                    <th>Tıklanma Sayısı</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($urls as $url): ?>
                    <tr>
                        <td><?php echo $i++ . "."; ?></td>
                        <td><?php echo $url['username']; ?></td>
                        <td><a href=<?php echo $url['short_code']; ?> target="_blank">http://localhost/<?php echo $url['short_code']; ?></a></td>
                        <td>
                            <a href="data:image/png;base64,<?php echo $url['qr_code']; ?>" data-bs-toggle="modal" data-bs-target="#qrModal">
                                <img src="data:image/png;base64,<?php echo $url['qr_code']; ?>" alt="QR Code" class="img-thumbnail" style="width:100px;">
                            </a>
                        </td>
                        <td><?php echo $url['click_count']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qrModalLabel">QR Kod</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body d-flex justify-content-center align-items-center">
        <img id="largeQrImage" src="" alt="Büyük QR Kod" class="img-fluid" style="max-width: 100%; max-height: 500px;">
      </div>
    </div>
  </div>
</div>

<script>
    $(document).ready(function() {
        if(localStorage.getItem('darkMode') === 'enabled'){
        $('body').addClass('dark-mode');
        $('#themeToggleButton').addClass('btn-dark').removeClass('btn-outline-light');
        $('#themeIcon').text('🌜');
        }
        
        $('#themeToggleButton').on('click', function() {
            if ($('body').hasClass('dark-mode')) {
                $('body').removeClass('dark-mode');
                $('#themeToggleButton').removeClass('btn-dark').addClass('btn-outline-light');
                $('#themeIcon').text('🌞');
                localStorage.setItem('darkMode', 'disabled');
            } else {
                $('body').addClass('dark-mode');
                $('#themeToggleButton').removeClass('btn-outline-light').addClass('btn-dark');
                $('#themeIcon').text('🌜');
                localStorage.setItem('darkMode', 'enabled');
            }
        });

        $('#urlTable').DataTable(
            {
            "responsive": true,
            "processing": true,
            "pagingType": "full_numbers",
            "language": {
                "processing":     "İşleniyor...",
                "lengthMenu":     "Sayfada _MENU_ kayıt göster",
                "zeroRecords":    "Kayıt bulunamadı",
                "info":           "Toplam _TOTAL_ kayıttan _START_ - _END_ arası gösteriliyor",
                "infoEmpty":      "Kayıt yok",
                "infoFiltered":   "(_MAX_ kayıttan filtrelendi)",
                "search":         "Ara:",
                "paginate": {
                    "first":    "İlk",
                    "last":     "Son",
                    "next":     "Sonraki",
                    "previous": "Önceki"
                }
            }
        });

        $('#qrModal').on('show.bs.modal', function (e) {
            var imageUrl = $(e.relatedTarget).attr('href');
            $('#largeQrImage').attr('src', imageUrl);
        });
    });
</script>

</body>
</html>
