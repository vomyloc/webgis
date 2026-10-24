<?php
require_once __DIR__ . '/ketnoi.php';

function googleOAuthConfig(): array
{
    $localConfig = [];
    $localConfigPath = __DIR__ . '/google_oauth_config.local.php';
    if (is_file($localConfigPath)) {
        $loadedConfig = require $localConfigPath;
        if (is_array($loadedConfig)) {
            $localConfig = $loadedConfig;
        }
    }

    $readConfigValue = static function (string $key, string $environmentVariable) use ($localConfig): string {
        $value = getenv($environmentVariable);
        if ($value === false || trim($value) === '') {
            $value = $localConfig[$key] ?? '';
        }

        return is_string($value) ? trim($value) : '';
    };

    return [
        'client_id' => $readConfigValue('client_id', 'WEBGIS_GOOGLE_CLIENT_ID'),
        'client_secret' => $readConfigValue('client_secret', 'WEBGIS_GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => $readConfigValue('redirect_uri', 'WEBGIS_GOOGLE_REDIRECT_URI')
    ];
}

require_once __DIR__ . '/google_oauth_native.php';

function googleOAuthFail(string $code): never
{
    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_nonce'], $_SESSION['google_oauth_code_verifier']);
    header('Location: login.php?google_error=' . rawurlencode($code));
    exit;
}

function googleOAuthLoginUser(array $claims): bool
{
    $googleId = (string)($claims['sub'] ?? '');
    $email = strtolower(trim((string)($claims['email'] ?? '')));
    $fullName = trim((string)($claims['name'] ?? ''));
    if ($googleId === '' || strlen($googleId) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 255 || ($claims['email_verified'] ?? false) !== true) {
        return false;
    }
    if ($fullName === '') $fullName = strstr($email, '@', true) ?: 'Google user';
    if (function_exists('mb_substr')) $fullName = mb_substr($fullName, 0, 100, 'UTF-8');
    else {
        $fullName = substr($fullName, 0, 100);
        $fullName = preg_replace('/[\x80-\xBF]+$/', '', $fullName);
    }

    $existing = db_fetch_one(
        "SELECT id, username, full_name, role, email, google_id
         FROM users
         WHERE google_id = $1 OR LOWER(email) = LOWER($2)
         ORDER BY (google_id = $1) DESC
         LIMIT 1",
        [$googleId, $email]
    );
    if ($existing === false) return false;

    if ($existing) {
        $linkedGoogleId = (string)($existing['google_id'] ?? '');
        if ($linkedGoogleId !== '' && !hash_equals($linkedGoogleId, $googleId)) return false;
        if ($linkedGoogleId === '') {
            $emailDomain = strtolower(substr(strrchr($email, '@'), 1));
            $hostedDomain = strtolower((string)($claims['hd'] ?? ''));
            if ($emailDomain !== 'gmail.com' && ($hostedDomain === '' || $emailDomain !== $hostedDomain)) return false;
            $linked = db_fetch_one(
                "UPDATE users SET google_id = $1 WHERE id = $2 AND (google_id IS NULL OR BTRIM(google_id) = '') RETURNING id",
                [$googleId, $existing['id']]
            );
            if ($linked === false || !$linked) return false;
        }
        $user = $existing;
    } else {
        $localPart = strstr($email, '@', true) ?: 'google-user';
        $base = strtolower((string)preg_replace('/[^a-z0-9._-]+/', '-', $localPart));
        $base = trim($base, '.-_');
        if ($base === '') $base = 'google-user';
        $suffix = substr(hash('sha256', $googleId), 0, 8);
        $username = substr($base, 0, 40) . '-' . $suffix;
        $randomPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $created = db_fetch_one(
            "INSERT INTO users (username, password_hash, full_name, email, google_id, role)
             VALUES ($1, $2, $3, $4, $5, 'user')
             RETURNING id, username, full_name, role, email, google_id",
            [$username, $randomPasswordHash, $fullName, $email, $googleId]
        );
        if ($created === false || !$created) return false;
        $user = $created;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    unset($_SESSION['csrf_token'], $_SESSION['google_oauth_state'], $_SESSION['google_oauth_nonce']);
    return true;
}
