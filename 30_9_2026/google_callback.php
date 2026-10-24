<?php
require_once __DIR__ . '/google_oauth.php';

if (!isset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_nonce'], $_SESSION['google_oauth_code_verifier'])
    || !is_string($_SESSION['google_oauth_state']) || !is_string($_SESSION['google_oauth_nonce'])
    || !is_string($_SESSION['google_oauth_code_verifier'])) {
    googleOAuthFail('state');
}
$expectedState = $_SESSION['google_oauth_state'];
$expectedNonce = $_SESSION['google_oauth_nonce'];
$codeVerifier = $_SESSION['google_oauth_code_verifier'];
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_code_verifier']);

$state = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
$code = is_string($_GET['code'] ?? null) ? $_GET['code'] : '';
if ($state === '' || !hash_equals($expectedState, $state)) googleOAuthFail('state');
if (isset($_GET['error'])) googleOAuthFail($_GET['error'] === 'access_denied' ? 'denied' : 'provider');
if ($code === '') googleOAuthFail('state');

if (!googleOAuthNativeConfigured()) googleOAuthFail('not_configured');
try {
    $token = googleOAuthExchangeCode($code, $codeVerifier);
    if (!is_array($token) || empty($token['id_token'])) googleOAuthFail('token');
    $claims = googleOAuthVerifyIdToken((string)$token['id_token'], $expectedNonce);
    if (!is_array($claims)) googleOAuthFail('token');
} catch (Throwable $exception) {
    error_log('WebGIS Google OAuth verification failed: ' . get_class($exception));
    googleOAuthFail('token');
}

if (!googleOAuthLoginUser($claims)) googleOAuthFail('account');
header('Location: index.php');
exit;
