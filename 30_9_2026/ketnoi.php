<?php
if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Có thể ghi đè cấu hình bằng biến môi trường khi triển khai lên máy chủ.
$host     = getenv('WEBGIS_DB_HOST') ?: 'localhost';
$port     = getenv('WEBGIS_DB_PORT') ?: '5432';
$dbname   = getenv('WEBGIS_DB_NAME') ?: 'Baitap_21092026';
$user     = getenv('WEBGIS_DB_USER') ?: 'postgres';
$password = getenv('WEBGIS_DB_PASSWORD') ?: '1234';

$quoteConnectionValue = static function ($value): string {
    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$value) . "'";
};
$conn_string = sprintf(
    'host=%s port=%s dbname=%s user=%s password=%s',
    $quoteConnectionValue($host),
    $quoteConnectionValue($port),
    $quoteConnectionValue($dbname),
    $quoteConnectionValue($user),
    $quoteConnectionValue($password)
);

$connectionWarning = '';
$ketnoi = false;
if (function_exists('pg_connect')) {
    set_error_handler(static function ($severity, $message) use (&$connectionWarning): bool {
        if ($severity === E_WARNING) $connectionWarning = (string)$message;
        return true;
    }, E_WARNING);
    try {
        $ketnoi = pg_connect($conn_string);
    } catch (Throwable $exception) {
        $connectionWarning = get_class($exception) . ': ' . $exception->getMessage();
    } finally {
        restore_error_handler();
    }
}

if (!$ketnoi) {
    if (!function_exists('pg_connect')) {
        $connectionWarning = 'PHP extension pgsql is not loaded.';
    }
    if ($password !== '') $connectionWarning = str_replace($password, '[redacted]', $connectionWarning);
    error_log(sprintf(
        'WebGIS PostgreSQL connection failed (host=%s port=%s database=%s user=%s): %s',
        $host,
        $port,
        $dbname,
        $user,
        $connectionWarning !== '' ? $connectionWarning : 'unknown connection error'
    ));
    if (!headers_sent()) {
        http_response_code(500);
        $isJsonEndpoint = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'xu_ly_click.php';
        $isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isJsonEndpoint || $isAjaxRequest) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Không thể kết nối CSDL PostgreSQL.']);
            exit;
        }
    }
    exit('Lỗi kết nối PostgreSQL. Hãy kiểm tra dịch vụ PostgreSQL, database và cấu hình WEBGIS_DB_* trong ketnoi.php hoặc Apache.');
}

if (function_exists('pg_set_client_encoding')) {
    @pg_set_client_encoding(webgisPgConnection(), 'UTF8');
}

/** Trả về kết nối PostgreSQL đang hoạt động với kiểu cụ thể cho PHP và IDE. */
function webgisPgConnection(): \PgSql\Connection
{
    global $ketnoi;

    if (!($ketnoi instanceof \PgSql\Connection)) {
        throw new RuntimeException('PostgreSQL connection is not available.');
    }

    return $ketnoi;
}

/**
 * Lấy một bản ghi bằng truy vấn PostgreSQL có tham số.
 * Trả về array khi tìm thấy, null khi không có bản ghi, false khi truy vấn lỗi.
 */
function db_fetch_one(string $sql, array $params = [])
{
    $connection = webgisPgConnection();
    $result = @pg_query_params($connection, $sql, $params);
    if ($result === false) {
        error_log('WebGIS PostgreSQL query error: ' . pg_last_error($connection));
        return false;
    }

    $row = pg_fetch_assoc($result);
    pg_free_result($result);

    return $row ?: null;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
