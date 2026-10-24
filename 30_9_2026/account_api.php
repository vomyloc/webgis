<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/ketnoi.php';

function accountRespond(int $httpStatus, string $status, string $message, array $extra = []): void
{
    http_response_code($httpStatus);
    echo json_encode(
        array_merge(['status' => $status, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function accountInput(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_scalar($value) ? trim((string)$value) : '';
}

function accountLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function accountQuery(string $sql, array $params = [])
{
    $connection = webgisPgConnection();
    $result = @pg_query_params($connection, $sql, $params);
    if ($result === false) {
        error_log('WebGIS account query error: ' . pg_last_error($connection));
    }
    return $result;
}

function accountBeginWrite(): bool
{
    $connection = webgisPgConnection();
    if (@pg_query($connection, 'BEGIN') === false) return false;
    if (@pg_query($connection, 'LOCK TABLE users IN SHARE ROW EXCLUSIVE MODE') === false) {
        @pg_query($connection, 'ROLLBACK');
        return false;
    }
    return true;
}

function accountRollback(): void
{
    @pg_query(webgisPgConnection(), 'ROLLBACK');
}

function accountRequireLockedAdmin(int $userId): void
{
    $user = db_fetch_one('SELECT role FROM users WHERE id = $1 FOR UPDATE', [$userId]);
    if ($user === false) {
        accountRollback();
        accountRespond(500, 'error', 'Không thể xác minh quyền quản trị.');
    }
    if (!$user || $user['role'] !== 'admin') {
        accountRollback();
        accountRespond(403, 'error', 'Quyền quản trị của tài khoản này không còn hiệu lực.');
    }
}

function accountCommit(): bool
{
    return @pg_query(webgisPgConnection(), 'COMMIT') !== false;
}

function accountDuplicate(string $username, string $email, ?int $excludeId = null): ?bool
{
    $sql = 'SELECT id FROM users WHERE (LOWER(username) = LOWER($1)
            OR ($2 <> \'\' AND LOWER(COALESCE(email, \'\')) = LOWER($2)))';
    $params = [$username, $email];
    if ($excludeId !== null) {
        $sql .= ' AND id <> $3';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $result = accountQuery($sql, $params);
    if ($result === false) return null;
    $duplicate = pg_fetch_assoc($result) !== false;
    pg_free_result($result);
    return $duplicate;
}

if (!isLoggedIn()) {
    accountRespond(401, 'error', 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
}
if (!isAdmin()) {
    accountRespond(403, 'error', 'Bạn không có quyền quản lý tài khoản.');
}

$currentUserId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
if ($currentUserId === false || $currentUserId < 1) {
    accountRespond(403, 'error', 'Phiên đăng nhập không hợp lệ.');
}

// Kiểm tra quyền hiện tại trong CSDL để phiên cũ không giữ quyền admin đã bị thu hồi.
$currentUser = db_fetch_one('SELECT id, role FROM users WHERE id = $1', [$currentUserId]);
if ($currentUser === false) {
    accountRespond(500, 'error', 'Không thể xác minh quyền quản trị.');
}
if (!$currentUser || $currentUser['role'] !== 'admin') {
    accountRespond(403, 'error', 'Quyền quản trị của tài khoản này không còn hiệu lực.');
}

$action = accountInput($_GET, 'action');
if ($action === 'list') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        accountRespond(405, 'error', 'Phương thức không được hỗ trợ.');
    }
    $search = accountInput($_GET, 'q');
    if (accountLength($search) > 120) $search = substr($search, 0, 120);
    $pattern = '%' . $search . '%';
    $usersResult = accountQuery(
        "SELECT id, username, full_name, email, role, created_at
         FROM users
         WHERE $1 = '%%' OR username ILIKE $1 OR full_name ILIKE $1 OR COALESCE(email, '') ILIKE $1
         ORDER BY CASE WHEN role = 'admin' THEN 0 ELSE 1 END, id DESC",
        [$pattern]
    );
    if ($usersResult === false) accountRespond(500, 'error', 'Không thể tải danh sách tài khoản.');
    $users = [];
    while ($row = pg_fetch_assoc($usersResult)) $users[] = $row;
    pg_free_result($usersResult);

    $stats = db_fetch_one(
        "SELECT COUNT(*)::int AS total,
                COUNT(*) FILTER (WHERE role = 'admin')::int AS admins,
                COUNT(*) FILTER (WHERE role = 'user')::int AS users
         FROM users"
    );
    if ($stats === false || !$stats) accountRespond(500, 'error', 'Không thể tải thống kê tài khoản.');
    accountRespond(200, 'success', 'Tải danh sách tài khoản thành công.', ['data' => $users, 'stats' => $stats]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    accountRespond(405, 'error', 'Phương thức không được hỗ trợ.');
}
if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
    accountRespond(403, 'error', 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.');
}

if ($action === 'create') {
    $username = accountInput($_POST, 'username');
    $fullName = accountInput($_POST, 'full_name');
    $email = strtolower(accountInput($_POST, 'email'));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $role = accountInput($_POST, 'role');

    if (accountLength($username) < 3 || accountLength($username) > 50) {
        accountRespond(422, 'error', 'Tên đăng nhập phải có từ 3 đến 50 ký tự.');
    }
    if ($fullName === '' || accountLength($fullName) > 100) {
        accountRespond(422, 'error', 'Vui lòng nhập họ tên (không quá 100 ký tự).');
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || accountLength($email) > 255)) {
        accountRespond(422, 'error', 'Email không hợp lệ.');
    }
    if (strlen($password) < 8 || strlen($password) > 4096) {
        accountRespond(422, 'error', 'Mật khẩu phải có từ 8 ký tự trở lên.');
    }
    if (!in_array($role, ['admin', 'user'], true)) accountRespond(422, 'error', 'Quyền tài khoản không hợp lệ.');

    if (!accountBeginWrite()) accountRespond(500, 'error', 'Không thể bắt đầu thao tác với CSDL.');
    accountRequireLockedAdmin((int)$currentUserId);
    $duplicate = accountDuplicate($username, $email);
    if ($duplicate === null) {
        accountRollback();
        accountRespond(500, 'error', 'Không thể kiểm tra tên đăng nhập hoặc email.');
    }
    if ($duplicate) {
        accountRollback();
        accountRespond(409, 'error', 'Tên đăng nhập hoặc email đã được sử dụng.');
    }
    $created = accountQuery(
        "INSERT INTO users (username, password_hash, full_name, email, role)
         VALUES ($1, $2, $3, NULLIF($4, ''), $5)
         RETURNING id, username, full_name, email, role, created_at",
        [$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $role]
    );
    if ($created === false) {
        accountRollback();
        accountRespond(500, 'error', 'Không thể tạo tài khoản. Vui lòng kiểm tra lại dữ liệu.');
    }
    $newUser = pg_fetch_assoc($created);
    pg_free_result($created);
    if (!accountCommit()) accountRespond(500, 'error', 'Không thể lưu tài khoản vào CSDL.');
    accountRespond(201, 'success', 'Đã tạo tài khoản mới.', ['data' => $newUser]);
}

if ($action === 'update') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) accountRespond(422, 'error', 'Tài khoản cần cập nhật không hợp lệ.');
    $username = accountInput($_POST, 'username');
    $fullName = accountInput($_POST, 'full_name');
    $email = strtolower(accountInput($_POST, 'email'));
    $newPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $role = accountInput($_POST, 'role');

    if (accountLength($username) < 3 || accountLength($username) > 50) {
        accountRespond(422, 'error', 'Tên đăng nhập phải có từ 3 đến 50 ký tự.');
    }
    if ($fullName === '' || accountLength($fullName) > 100) {
        accountRespond(422, 'error', 'Vui lòng nhập họ tên (không quá 100 ký tự).');
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || accountLength($email) > 255)) {
        accountRespond(422, 'error', 'Email không hợp lệ.');
    }
    if ($newPassword !== '' && (strlen($newPassword) < 8 || strlen($newPassword) > 4096)) {
        accountRespond(422, 'error', 'Mật khẩu mới phải có từ 8 ký tự trở lên.');
    }
    if (!in_array($role, ['admin', 'user'], true)) accountRespond(422, 'error', 'Quyền tài khoản không hợp lệ.');

    if (!accountBeginWrite()) accountRespond(500, 'error', 'Không thể bắt đầu thao tác với CSDL.');
    accountRequireLockedAdmin((int)$currentUserId);
    $target = db_fetch_one('SELECT id, role FROM users WHERE id = $1 FOR UPDATE', [$id]);
    if ($target === false) {
        accountRollback();
        accountRespond(500, 'error', 'Không thể đọc tài khoản cần cập nhật.');
    }
    if (!$target) {
        accountRollback();
        accountRespond(404, 'error', 'Không tìm thấy tài khoản cần cập nhật.');
    }
    if ((int)$id === (int)$currentUserId && $role !== 'admin') {
        accountRollback();
        accountRespond(422, 'error', 'Bạn không thể tự hạ quyền quản trị của mình.');
    }
    if ($target['role'] === 'admin' && $role !== 'admin') {
        $adminCount = db_fetch_one("SELECT COUNT(*)::int AS total FROM users WHERE role = 'admin'");
        if ($adminCount === false || !$adminCount) {
            accountRollback();
            accountRespond(500, 'error', 'Không thể kiểm tra số lượng quản trị viên.');
        }
        if ((int)$adminCount['total'] <= 1) {
            accountRollback();
            accountRespond(422, 'error', 'Hệ thống phải có ít nhất một quản trị viên.');
        }
    }
    $duplicate = accountDuplicate($username, $email, (int)$id);
    if ($duplicate === null) {
        accountRollback();
        accountRespond(500, 'error', 'Không thể kiểm tra tên đăng nhập hoặc email.');
    }
    if ($duplicate) {
        accountRollback();
        accountRespond(409, 'error', 'Tên đăng nhập hoặc email đã được sử dụng.');
    }

    $passwordHash = $newPassword === '' ? '' : password_hash($newPassword, PASSWORD_DEFAULT);
    $updated = accountQuery(
        "UPDATE users
         SET username = $1, full_name = $2, email = NULLIF($3, ''), role = $4,
             password_hash = CASE WHEN $5 = '' THEN password_hash ELSE $5 END
         WHERE id = $6
         RETURNING id, username, full_name, email, role, created_at",
        [$username, $fullName, $email, $role, $passwordHash, $id]
    );
    if ($updated === false) {
        accountRollback();
        accountRespond(500, 'error', 'Không thể cập nhật tài khoản. Vui lòng kiểm tra lại dữ liệu.');
    }
    $updatedUser = pg_fetch_assoc($updated);
    pg_free_result($updated);
    if (!accountCommit()) accountRespond(500, 'error', 'Không thể lưu thay đổi vào CSDL.');
    if ((int)$id === (int)$currentUserId) {
        $_SESSION['username'] = $username;
        $_SESSION['full_name'] = $fullName;
    }
    accountRespond(200, 'success', 'Đã cập nhật tài khoản.', ['data' => $updatedUser]);
}

if ($action === 'delete') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) accountRespond(422, 'error', 'Tài khoản cần xóa không hợp lệ.');
    if ((int)$id === (int)$currentUserId) accountRespond(422, 'error', 'Bạn không thể xóa tài khoản đang đăng nhập.');

    if (!accountBeginWrite()) accountRespond(500, 'error', 'Không thể bắt đầu thao tác với CSDL.');
    accountRequireLockedAdmin((int)$currentUserId);
    $target = db_fetch_one('SELECT id, role FROM users WHERE id = $1 FOR UPDATE', [$id]);
    if ($target === false) {
        accountRollback();
        accountRespond(500, 'error', 'Không thể đọc tài khoản cần xóa.');
    }
    if (!$target) {
        accountRollback();
        accountRespond(404, 'error', 'Không tìm thấy tài khoản cần xóa.');
    }
    if ($target['role'] === 'admin') {
        $adminCount = db_fetch_one("SELECT COUNT(*)::int AS total FROM users WHERE role = 'admin'");
        if ($adminCount === false || !$adminCount) {
            accountRollback();
            accountRespond(500, 'error', 'Không thể kiểm tra số lượng quản trị viên.');
        }
        if ((int)$adminCount['total'] <= 1) {
            accountRollback();
            accountRespond(422, 'error', 'Không thể xóa quản trị viên cuối cùng của hệ thống.');
        }
    }
    $deleted = accountQuery('DELETE FROM users WHERE id = $1 RETURNING id', [$id]);
    if ($deleted === false) {
        accountRollback();
        accountRespond(500, 'error', 'Không thể xóa tài khoản.');
    }
    pg_free_result($deleted);
    if (!accountCommit()) accountRespond(500, 'error', 'Không thể hoàn tất thao tác xóa tài khoản.');
    accountRespond(200, 'success', 'Đã xóa tài khoản.');
}

accountRespond(404, 'error', 'Thao tác tài khoản không hợp lệ.');
