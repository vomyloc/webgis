<?php
require_once __DIR__ . '/ketnoi.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';
$fullName = '';
$email = '';

function webgisStringLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        $error = 'Phiên biểu mẫu đã hết hạn hoặc không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } else {
        $username = trim(is_scalar($_POST['username'] ?? null) ? (string)$_POST['username'] : '');
        $fullName = trim(is_scalar($_POST['full_name'] ?? null) ? (string)$_POST['full_name'] : '');
        $email = strtolower(trim(is_scalar($_POST['email'] ?? null) ? (string)$_POST['email'] : ''));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        if (webgisStringLength($username) < 3 || webgisStringLength($username) > 50) {
            $error = 'Tên đăng nhập phải có từ 3 đến 50 ký tự.';
        } elseif ($fullName === '' || webgisStringLength($fullName) > 100) {
            $error = 'Vui lòng nhập họ tên (không quá 100 ký tự).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || webgisStringLength($email) > 255) {
            $error = 'Email không hợp lệ.';
        } elseif (strlen($password) < 8) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } else {
            $existingUser = db_fetch_one(
                "SELECT id FROM users WHERE username = $1 OR LOWER(email) = LOWER($2) LIMIT 1",
                [$username, $email]
            );
            if ($existingUser === false) {
                $error = 'Không thể kiểm tra tài khoản. Vui lòng kiểm tra cấu hình CSDL.';
            } elseif ($existingUser) {
                $error = 'Tên đăng nhập hoặc email đã được sử dụng.';
            } else {
                $newUser = db_fetch_one(
                    "INSERT INTO users (username, password_hash, full_name, email, role)
                     VALUES ($1, $2, $3, $4, 'user') RETURNING id",
                    [$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email]
                );
                if ($newUser === false || !$newUser) {
                    $error = 'Không thể tạo tài khoản. Vui lòng kiểm tra cấu hình CSDL.';
                } else {
                    $_SESSION['register_success'] = 'Đăng ký thành công. Vui lòng đăng nhập bằng tài khoản vừa tạo.';
                    header('Location: login.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="register-document">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - WebGIS Địa Chính</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="demo.css">
</head>
<body class="login-bg register-page d-flex align-items-center justify-content-center min-vh-100 py-4">
    <main class="card login-card border-0 shadow-lg" style="max-width: 480px; width: 100%;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="icon-badge mx-auto mb-3"><i class="bi bi-person-plus fs-2 text-primary"></i></div>
                <h4 class="fw-bold text-dark mb-1">Tạo tài khoản WebGIS</h4>
                <p class="text-muted small mb-0">Tài khoản mới được cấp quyền người dùng tra cứu.</p>
            </div>
            <?php if ($error !== ''): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <form method="POST" action="register.php" autocomplete="on">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3"><label for="full_name" class="form-label small fw-semibold text-secondary">Họ và tên</label><input id="full_name" type="text" name="full_name" maxlength="100" class="form-control" value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="name"></div>
                <div class="mb-3"><label for="username" class="form-label small fw-semibold text-secondary">Tên đăng nhập</label><input id="username" type="text" name="username" minlength="3" maxlength="50" class="form-control" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username"></div>
                <div class="mb-3"><label for="email" class="form-label small fw-semibold text-secondary">Email</label><input id="email" type="email" name="email" maxlength="255" class="form-control" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email"></div>
                <div class="mb-3"><label for="password" class="form-label small fw-semibold text-secondary">Mật khẩu (ít nhất 8 ký tự)</label><input id="password" type="password" name="password" minlength="8" class="form-control" required autocomplete="new-password"></div>
                <div class="mb-3"><label for="confirm_password" class="form-label small fw-semibold text-secondary">Xác nhận mật khẩu</label><input id="confirm_password" type="password" name="confirm_password" minlength="8" class="form-control" required autocomplete="new-password"></div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-person-check me-1"></i>Tạo tài khoản</button>
            </form>
            <div class="text-center mt-3 small"><span class="text-muted">Đã có tài khoản?</span> <a href="login.php" class="fw-bold text-primary text-decoration-none">Đăng nhập</a></div>
        </div>
    </main>
</body>
</html>
