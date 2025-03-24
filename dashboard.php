<?php
require 'utility/config.php';
require 'vendor/phpqrcode/qrlib.php';
session_start();

$user_id = $_SESSION['user_id'];

if (!isset($user_id)) {
    header("Location: index.php");
    exit;
}

function generateShortCode($length = 6) {
    return substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, $length);
}

if (isset($_POST['original_url'])) {
    $original_url = filter_var($_POST['original_url'], FILTER_SANITIZE_URL);
    $kontrol = $conn->prepare("SELECT original_url FROM urls WHERE original_url = ? AND user_id = ?");
    $kontrol->execute([$original_url, $user_id]);    
    if($original_url == $kontrol->fetchColumn()) {
        $varolanURL = $conn->prepare("SELECT short_code FROM urls WHERE original_url = ? AND user_id = ?");
        $varolanURL->execute([$original_url,$user_id]);
        $varolanURL = $varolanURL->fetchColumn();
        $short_url = "http://localhost/" . $varolanURL;
        echo "<div class='alert alert-danger'>URL daha önce kısaltılmış!<br>Kısaltılmış URL: <a href='$short_url'>$short_url</a></div>";
    } else {
        $short_code = generateShortCode();
    
        $stmt = $conn->prepare("INSERT INTO urls (original_url, short_code, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$original_url, $short_code, $user_id]);        
    
        $short_url = "http://localhost/" . $short_code;

        ob_start();
        QRcode::png($short_url, null, QR_ECLEVEL_L, 10, 1);
        $qr_image = ob_get_contents();
        ob_end_clean();

        $base64_qr = base64_encode($qr_image);
        $stmt = $conn->prepare("UPDATE urls SET qr_code = ? WHERE short_code = ?");
        $stmt->execute([$base64_qr, $short_code]);

        echo "<div class='alert alert-success'>
                Kısaltılmış URL: <a href='$short_url'>$short_url</a><br>
                <img src='data:image/png;base64,$base64_qr' alt='QR Code' class='mt-3' style='width:150px;'>
            </div>";
    }
}

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $stmt = $conn->prepare("SELECT original_url FROM urls WHERE short_code = ?");
    $stmt->execute([$code]);
    $url = $stmt->fetchColumn();

    if ($url) {
        header("Location: $url");
        exit;
    } else {
        echo "<div class='alert alert-danger'>URL bulunamadı.</div>";
    }
}

$stmt = $conn->prepare("
    SELECT 
        u.short_code, 
        u.original_url, 
        u.qr_code, 
        COUNT(c.id) AS click_count
    FROM 
        urls u
    LEFT JOIN 
        clicks c ON u.id = c.url_id
    WHERE 
        u.user_id = ?
    GROUP BY 
        u.id
");
$stmt->execute([$user_id]);
$urls = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$username = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="tr" >
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL ve QR Oluşturucu</title>
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
        <h2 class="text-center">URL ve QR Oluşturucuya Hoşgeldin <?php echo htmlspecialchars($username); ?>!</h2>
        <p class="text-center">Burada kişisel URL'lerinizi görüntüleyebilir ve yönetebilirsiniz.</p>
        <form method="POST">
            <div class="mb-3">
                <label for="original_url" class="form-label">Uzun URL</label>
                <input type="url" name="original_url" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Kısalt</button>
        </form>
    </div>
    <div class="mt-4">
        <table id="urlTable" class="table table-striped">
            <thead>
                <tr>
                    <th>Kısa URL</th>
                    <th>Uzun URL</th>
                    <th>QR Kod</th>
                    <th>Tıklanma Sayısı</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($urls as $url): ?>
                    <tr>
                        <td><a href=<?php echo $url['short_code']; ?> target="_blank">http://localhost/<?php echo $url['short_code']; ?></a></td>
                        <td><?php echo htmlspecialchars($url['original_url']); ?></td>
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
        
        $('#urlTable').DataTable({
            "responsive": true,
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "utility/fetch_urls.php",
                "type": "GET",
                "dataSrc": function (json) {
                    if (!json.data) {
                        console.log("Veri hatalı");
                        return [];
                    }
                    return json.data;
                },
                "error": function (xhr, error, code) {
                    console.log("AJAX Error:", error, code);
                    alert("Veriler yüklenirken bir hata oluştu.");
                }
            },
            "columns": [
                {
                    "data": "short_code",
                    "render": function(data, type, row, meta) {
                        return '<a href="' + data + '" target="_blank">http://localhost/' + data + '</a>';
                    }
                },
                { "data": "original_url" },
                {
                    "data": "qr_code",
                    "render": function(data, type, row, meta) {
                        return '<a href="data:image/png;base64,' + data + '" data-bs-toggle="modal" data-bs-target="#qrModal"><img src="data:image/png;base64,' + data + '" alt="QR Code" class="img-thumbnail" style="width:100px;"></a>';
                    }
                },
                { "data": "click_count" }
            ],
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
