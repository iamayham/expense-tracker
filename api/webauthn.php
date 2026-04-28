<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

verifyCsrf();

if (!isHttpsRequest()) {
    jsonResponse(false, 'Passkeys require HTTPS (or localhost).', [], 400);
}

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoloadPath)) {
    jsonResponse(false, 'WebAuthn dependency missing. Run: composer install', [], 500);
}
require_once $autoloadPath;

if (!class_exists(\lbuchs\WebAuthn\WebAuthn::class)) {
    jsonResponse(false, 'WebAuthn library could not be loaded.', [], 500);
}

$action = (string) ($_POST['action'] ?? '');
$rpId = webauthnRpId();
$origin = webauthnOrigin();
$webAuthn = new \lbuchs\WebAuthn\WebAuthn(appName(), $rpId, ['none'], true);

if ($action === 'register_begin') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!validateEmail($email) || $password === '') {
        jsonResponse(false, 'Enter email and password first.', [], 422);
    }

    $statement = db()->prepare('SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        jsonResponse(false, 'Invalid email or password.', [], 401);
    }

    $excludeRows = db()->prepare('SELECT credential_id FROM user_passkeys WHERE user_id = :user_id');
    $excludeRows->execute(['user_id' => (int) $user['id']]);
    $excludeCredentialIds = [];
    foreach ($excludeRows->fetchAll() as $row) {
        $rawCredentialId = webauthnDecodeBase64Flexible((string) $row['credential_id']);
        if ($rawCredentialId !== '') {
            $excludeCredentialIds[] = $rawCredentialId;
        }
    }

    $createArgs = $webAuthn->getCreateArgs(
        (string) $user['id'],
        (string) $user['email'],
        (string) $user['name'],
        180,
        'required',
        'required',
        false,
        $excludeCredentialIds
    );
    $createOptions = webauthnNormalizeOptions($createArgs);

    $challengeBuffer = $webAuthn->getChallenge();
    $challenge = is_object($challengeBuffer) && method_exists($challengeBuffer, 'getBinaryString')
        ? $challengeBuffer->getBinaryString()
        : (string) $challengeBuffer;
    if ($challenge === '') {
        jsonResponse(false, 'Could not start passkey registration.', [], 500);
    }

    $_SESSION['webauthn_register'] = [
        'user_id' => (int) $user['id'],
        'challenge' => base64_encode($challenge),
        'origin' => $origin,
        'rp_id' => $rpId,
        'created_at' => time(),
    ];

    jsonResponse(true, 'Passkey registration started.', [
        'publicKey' => $createOptions,
    ]);
}

if ($action === 'register_finish') {
    $state = $_SESSION['webauthn_register'] ?? null;
    if (!is_array($state) || (time() - (int) ($state['created_at'] ?? 0)) > 300) {
        jsonResponse(false, 'Registration session expired. Try again.', [], 419);
    }

    $clientDataJSON = (string) ($_POST['clientDataJSON'] ?? '');
    $attestationObject = (string) ($_POST['attestationObject'] ?? '');
    $transports = (string) ($_POST['transports'] ?? '[]');

    if ($clientDataJSON === '' || $attestationObject === '') {
        jsonResponse(false, 'Invalid passkey registration payload.', [], 422);
    }
    if (($state['origin'] ?? '') !== $origin || ($state['rp_id'] ?? '') !== $rpId) {
        jsonResponse(false, 'Origin or RP ID mismatch.', [], 403);
    }

    try {
        $credential = $webAuthn->processCreate(
            webauthnDecodeBase64Flexible($clientDataJSON),
            webauthnDecodeBase64Flexible($attestationObject),
            webauthnDecodeBase64Flexible((string) ($state['challenge'] ?? '')),
            true,
            true,
            false,
            false
        );
    } catch (Throwable $exception) {
        jsonResponse(false, 'Passkey registration verification failed: ' . $exception->getMessage(), [], 422);
    }

    $credentialIdRaw = (string) ($credential->credentialId ?? '');
    $credentialId = webauthnEncodeBase64Url($credentialIdRaw);
    $publicKey = base64_encode((string) ($credential->credentialPublicKey ?? ''));
    $signCount = (int) ($credential->signCount ?? 0);

    if ($credentialId === '' || $publicKey === '') {
        jsonResponse(false, 'Invalid credential data.', [], 422);
    }

    $upsert = db()->prepare(
        'INSERT INTO user_passkeys (user_id, credential_id, public_key, sign_count, transports, created_at, updated_at)
         VALUES (:user_id, :credential_id, :public_key, :sign_count, :transports, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            public_key = VALUES(public_key),
            sign_count = VALUES(sign_count),
            transports = VALUES(transports),
            updated_at = NOW()'
    );
    $upsert->execute([
        'user_id' => (int) $state['user_id'],
        'credential_id' => $credentialId,
        'public_key' => $publicKey,
        'sign_count' => $signCount,
        'transports' => $transports,
    ]);

    unset($_SESSION['webauthn_register']);

    jsonResponse(true, 'Passkey registered successfully.');
}

if ($action === 'login_begin') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!validateEmail($email)) {
        jsonResponse(false, 'Please enter your email first.', [], 422);
    }

    $statement = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $userId = (int) $statement->fetchColumn();
    if ($userId <= 0) {
        jsonResponse(false, 'No account found for this email.', [], 404);
    }

    $credentialRows = db()->prepare('SELECT credential_id FROM user_passkeys WHERE user_id = :user_id');
    $credentialRows->execute(['user_id' => $userId]);
    $credentialIds = [];
    foreach ($credentialRows->fetchAll() as $row) {
        $rawId = webauthnDecodeBase64Flexible((string) $row['credential_id']);
        if ($rawId !== '') {
            $credentialIds[] = $rawId;
        }
    }

    if (!$credentialIds) {
        jsonResponse(false, 'No passkey found for this account. Register one first.', [], 404);
    }

    $getArgs = $webAuthn->getGetArgs(
        $credentialIds,
        180,
        false,
        false,
        false,
        false,
        true,
        'required'
    );
    $getOptions = webauthnNormalizeOptions($getArgs);
    $challengeBuffer = $webAuthn->getChallenge();
    $challenge = is_object($challengeBuffer) && method_exists($challengeBuffer, 'getBinaryString')
        ? $challengeBuffer->getBinaryString()
        : (string) $challengeBuffer;
    if ($challenge === '') {
        jsonResponse(false, 'Could not start passkey login.', [], 500);
    }

    $_SESSION['webauthn_login'] = [
        'user_id' => $userId,
        'challenge' => base64_encode($challenge),
        'origin' => $origin,
        'rp_id' => $rpId,
        'created_at' => time(),
    ];

    jsonResponse(true, 'Passkey login started.', [
        'publicKey' => $getOptions,
    ]);
}

if ($action === 'login_finish') {
    $state = $_SESSION['webauthn_login'] ?? null;
    if (!is_array($state) || (time() - (int) ($state['created_at'] ?? 0)) > 300) {
        jsonResponse(false, 'Login session expired. Try again.', [], 419);
    }

    $credentialIdB64 = (string) ($_POST['credentialId'] ?? '');
    $clientDataJSON = (string) ($_POST['clientDataJSON'] ?? '');
    $authenticatorData = (string) ($_POST['authenticatorData'] ?? '');
    $signature = (string) ($_POST['signature'] ?? '');
    $rememberMe = ($_POST['remember_me'] ?? '') === '1';

    if ($credentialIdB64 === '' || $clientDataJSON === '' || $authenticatorData === '' || $signature === '') {
        jsonResponse(false, 'Invalid passkey login payload.', [], 422);
    }
    if (($state['origin'] ?? '') !== $origin || ($state['rp_id'] ?? '') !== $rpId) {
        jsonResponse(false, 'Origin or RP ID mismatch.', [], 403);
    }

    $credentialId = base64_decode($credentialIdB64, true);
    if ($credentialId === false || $credentialId === '') {
        $credentialId = webauthnDecodeBase64Flexible($credentialIdB64);
    }
    if ($credentialId === '') {
        jsonResponse(false, 'Invalid credential id.', [], 422);
    }

    $credentialIdBase64Url = webauthnEncodeBase64Url($credentialId);
    $credentialIdBase64 = base64_encode($credentialId);

    $statement = db()->prepare(
        'SELECT up.id, up.user_id, up.credential_id, up.public_key, up.sign_count, u.name, u.email, u.avatar_url, u.preferred_currency, u.theme_preference
         FROM user_passkeys up
         INNER JOIN users u ON u.id = up.user_id
         WHERE up.credential_id = :credential_id_base64url
            OR up.credential_id = :credential_id_base64
         LIMIT 1'
    );
    $statement->execute([
        'credential_id_base64url' => $credentialIdBase64Url,
        'credential_id_base64' => $credentialIdBase64,
    ]);
    $passkeyRow = $statement->fetch();
    if (!$passkeyRow) {
        jsonResponse(false, 'Passkey not recognized.', [], 404);
    }

    try {
        $webAuthn->processGet(
            webauthnDecodeBase64Flexible($clientDataJSON),
            webauthnDecodeBase64Flexible($authenticatorData),
            webauthnDecodeBase64Flexible($signature),
            base64_decode((string) $passkeyRow['public_key'], true) ?: '',
            webauthnDecodeBase64Flexible((string) ($state['challenge'] ?? '')),
            (int) $passkeyRow['sign_count'],
            true,
            true
        );
    } catch (Throwable $exception) {
        jsonResponse(false, 'Passkey verification failed: ' . $exception->getMessage(), [], 401);
    }

    $newSignCount = $webAuthn->getSignatureCounter();

    $update = db()->prepare('UPDATE user_passkeys SET sign_count = :sign_count, updated_at = NOW() WHERE id = :id');
    $update->execute([
        'sign_count' => (int) $newSignCount,
        'id' => (int) $passkeyRow['id'],
    ]);

    completeUserLogin([
        'id' => (int) $passkeyRow['user_id'],
        'name' => (string) $passkeyRow['name'],
        'email' => (string) $passkeyRow['email'],
        'avatar_url' => (string) ($passkeyRow['avatar_url'] ?? ''),
        'preferred_currency' => (string) ($passkeyRow['preferred_currency'] ?? 'USD'),
        'theme_preference' => (string) ($passkeyRow['theme_preference'] ?? 'light'),
    ], $rememberMe);

    unset($_SESSION['webauthn_login']);

    jsonResponse(true, 'Logged in with passkey successfully.', [
        'redirect' => url(''),
    ]);
}

jsonResponse(false, 'Unsupported WebAuthn action.', [], 400);
