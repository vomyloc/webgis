<?php
require_once __DIR__ . '/google_oauth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if (!googleOAuthNativeConfigured()) googleOAuthFail('not_configured');

$state = bin2hex(random_bytes(32));
$nonce = bin2hex(random_bytes(32));
$codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_nonce'] = $nonce;
$_SESSION['google_oauth_code_verifier'] = $codeVerifier;
header('Location: ' . googleOAuthNativeAuthUrl($state, $nonce, $codeChallenge));
exit;
