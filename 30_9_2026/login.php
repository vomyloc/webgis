<?php
require_once __DIR__ . '/ketnoi.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$successMsg = $_SESSION['register_success'] ?? '';
unset($_SESSION['register_success']);
$googleErrors = [
    'not_configured' => 'Đăng nhập Google chưa được cấu hình. Hãy khai báo OAuth Client ID, Client Secret cùng Redirect URI trong Apache.',
    'denied' => 'Bạn đã hủy đăng nhập Google.',
    'state' => 'Yêu cầu đăng nhập Google hết hạn hoặc không hợp lệ. Vui lòng thử lại.',
    'provider' => 'Google không thể hoàn tất đăng nhập. Vui lòng thử lại.',
    'token' => 'Không xác minh được phản hồi đăng nhập từ Google. Vui lòng thử lại.',
    'account' => 'Không thể liên kết hoặc tạo tài khoản WebGIS. Nếu bạn đã có tài khoản, hãy đăng nhập bằng mật khẩu trước hoặc liên hệ quản trị viên.',
];
$googleError = is_string($_GET['google_error'] ?? null) ? $_GET['google_error'] : '';
if ($googleError !== '') $error = $googleErrors[$googleError] ?? 'Đăng nhập Google thất bại. Vui lòng thử lại.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        $error = 'Phiên biểu mẫu đã hết hạn hoặc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } else {
        $username = trim(is_scalar($_POST['username'] ?? null) ? (string)$_POST['username'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($username === '' || $password === '') {
            $error = 'Vui lòng nhập đầy đủ tên đăng nhập/email và mật khẩu.';
        } else {
            $userData = db_fetch_one(
                "SELECT id, username, password_hash, full_name, role
                 FROM users
                 WHERE username = $1 OR LOWER(email) = LOWER($2)
                 ORDER BY (username = $1) DESC
                 LIMIT 1",
                [$username, $username]
            );
            if ($userData === false) {
                $error = 'Không thể truy vấn tài khoản. Vui lòng kiểm tra cấu hình CSDL.';
            } elseif ($userData && password_verify($password, (string)$userData['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['username'] = $userData['username'];
                $_SESSION['full_name'] = $userData['full_name'];
                $_SESSION['role'] = $userData['role'];
                header('Location: index.php');
                exit;
            } elseif ($userData) {
                $error = 'Mật khẩu không chính xác.';
            } else {
                $error = 'Tài khoản không tồn tại. Bạn có thể đăng ký mới bên dưới.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - WebGIS Địa Chính</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="demo.css">
</head>
<body class="login-bg d-flex align-items-center justify-content-center min-vh-100 py-4">
    <main class="card login-card border-0 shadow-lg" style="max-width: 440px; width: 100%;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="icon-badge mx-auto mb-3"><i class="bi bi-globe-americas fs-2 text-primary"></i></div>
                <h4 class="fw-bold text-dark mb-1">WebGIS Địa Chính</h4>
                <p class="text-muted small mb-0">Hệ thống Tra cứu & Quản lý Thông tin Thửa đất</p>
            </div>
            <?php if ($successMsg !== ''): ?>
                <div class="alert alert-success py-2 small"><?= htmlspecialchars((string)$successMsg, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST" action="login.php" autocomplete="off">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Tên đăng nhập hoặc Email</label>
                    <input type="text" name="username" autocomplete="username" class="form-control" placeholder="Nhập username hoặc email..." required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Mật khẩu</label>
                    <input type="password" name="password" autocomplete="current-password" class="form-control" placeholder="Nhập mật khẩu..." required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập hệ thống</button>
            </form>
            <div class="d-flex align-items-center my-3">
                <hr class="flex-grow-1 text-secondary opacity-25"><span class="px-2 small text-muted">hoặc tiếp tục với</span><hr class="flex-grow-1 text-secondary opacity-25">
            </div>
            <a href="google_login.php" class="btn btn-outline-dark w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 bg-white">
                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                <span>Đăng nhập bằng Google</span>
            </a>
            <div class="text-center mt-3 small"><span class="text-muted">Chưa có tài khoản?</span> <a href="register.php" class="fw-bold text-primary text-decoration-none ms-1">Đăng ký ngay</a></div>
        </div>
    </main>
</body>
</html>
