<?php

function googleOAuthNativeConfigured(): bool
{
    $config = googleOAuthConfig();
    return $config['client_id'] !== '' && $config['client_secret'] !== '' && $config['redirect_uri'] !== '';
}

/** HTTPS-only request helper with certificate checks enabled. */
function googleOAuthHttpRequest(string $method, string $url, string $body = ''): array
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($curl);
        curl_close($curl);
        if ($responseBody === false || $curlError !== 0) return ['status' => 0, 'body' => ''];
        return ['status' => $status, 'body' => (string)$responseBody];
    }

    $headers = "Accept: application/json\r\n";
    $options = ['method' => $method, 'timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0, 'header' => $headers];
    if ($method === 'POST') {
        $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['content'] = $body;
    }
    $context = stream_context_create([
        'http' => $options,
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) $status = (int)$matches[1];
    }
    return ['status' => $responseBody === false ? 0 : $status, 'body' => $responseBody === false ? '' : $responseBody];
}

function googleOAuthNativeAuthUrl(string $state, string $nonce, string $codeChallenge): string
{
    $config = googleOAuthConfig();
    $query = http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account'
    ], '', '&', PHP_QUERY_RFC3986);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
}

function googleOAuthExchangeCode(string $code, string $codeVerifier)
{
    $config = googleOAuthConfig();
    $form = http_build_query([
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code',
        'code_verifier' => $codeVerifier
    ], '', '&', PHP_QUERY_RFC3986);
    $response = googleOAuthHttpRequest('POST', 'https://oauth2.googleapis.com/token', $form);
    $payload = $response['status'] === 200 ? json_decode($response['body'], true) : null;
    return is_array($payload) && !empty($payload['id_token']) ? $payload : false;
}

function googleOAuthBase64UrlDecode(string $value)
{
    $value = strtr($value, '-_', '+/');
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode($value, true);
}

function googleOAuthDerLength(int $length): string
{
    if ($length < 128) return chr($length);
    $bytes = '';
    while ($length > 0) { $bytes = chr($length & 0xff) . $bytes; $length >>= 8; }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function googleOAuthDerInteger(string $value): string
{
    $value = ltrim($value, "\x00");
    if ($value === '') $value = "\x00";
    if ((ord($value[0]) & 0x80) !== 0) $value = "\x00" . $value;
    return "\x02" . googleOAuthDerLength(strlen($value)) . $value;
}

function googleOAuthJwkToPem(array $jwk)
{
    $modulus = googleOAuthBase64UrlDecode((string)($jwk['n'] ?? ''));
    $exponent = googleOAuthBase64UrlDecode((string)($jwk['e'] ?? ''));
    if ($modulus === false || $exponent === false || $modulus === '' || $exponent === '') return false;
    $rsaBody = googleOAuthDerInteger($modulus) . googleOAuthDerInteger($exponent);
    $rsaKey = "\x30" . googleOAuthDerLength(strlen($rsaBody)) . $rsaBody;
    $rsaAlgorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $bitStringBody = "\x00" . $rsaKey;
    $bitString = "\x03" . googleOAuthDerLength(strlen($bitStringBody)) . $bitStringBody;
    $spkiBody = $rsaAlgorithm . $bitString;
    $spki = "\x30" . googleOAuthDerLength(strlen($spkiBody)) . $spkiBody;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** Verify the RS256 signature with Google's published public keys and validate OIDC claims. */
function googleOAuthVerifyIdToken(string $idToken, string $expectedNonce)
{
    if (!function_exists('openssl_verify')) return false;
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) return false;
    $headerJson = googleOAuthBase64UrlDecode($parts[0]);
    $claimsJson = googleOAuthBase64UrlDecode($parts[1]);
    $signature = googleOAuthBase64UrlDecode($parts[2]);
    if ($headerJson === false || $claimsJson === false || $signature === false) return false;
    $header = json_decode($headerJson, true);
    $claims = json_decode($claimsJson, true);
    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) return false;

    $keyResponse = googleOAuthHttpRequest('GET', 'https://www.googleapis.com/oauth2/v3/certs');
    $keySet = $keyResponse['status'] === 200 ? json_decode($keyResponse['body'], true) : null;
    if (!is_array($keySet['keys'] ?? null)) return false;
    $publicKeyPem = false;
    foreach ($keySet['keys'] as $jwk) {
        if (($jwk['kid'] ?? '') === $header['kid'] && ($jwk['kty'] ?? '') === 'RSA'
            && (!isset($jwk['alg']) || $jwk['alg'] === 'RS256') && (!isset($jwk['use']) || $jwk['use'] === 'sig')) {
            $publicKeyPem = googleOAuthJwkToPem($jwk);
            break;
        }
    }
    if (!$publicKeyPem) return false;
    $publicKey = openssl_pkey_get_public($publicKeyPem);
    if ($publicKey === false) return false;
    $signatureValid = openssl_verify($parts[0] . '.' . $parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    if (function_exists('openssl_free_key')) openssl_free_key($publicKey);
    if (!$signatureValid) return false;

    $clientId = googleOAuthConfig()['client_id'];
    $now = time();
    if (!in_array((string)($claims['iss'] ?? ''), ['https://accounts.google.com', 'accounts.google.com'], true)
        || !hash_equals($clientId, (string)($claims['aud'] ?? ''))
        || (isset($claims['azp']) && !hash_equals($clientId, (string)$claims['azp']))
        || !isset($claims['exp'], $claims['iat']) || (int)$claims['exp'] < $now - 30
        || (int)$claims['iat'] > $now + 60
        || !isset($claims['nonce']) || !hash_equals($expectedNonce, (string)$claims['nonce'])
        || ($claims['email_verified'] ?? false) !== true) {
        return false;
    }
    return $claims;
}
