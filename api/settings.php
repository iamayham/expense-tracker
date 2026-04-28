<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = currentUserId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $user = getCurrentUser($userId);

    if (!$user) {
        jsonResponse(false, 'User profile could not be loaded.', [], 404);
    }

    jsonResponse(true, 'Settings loaded successfully.', [
        'user' => $user,
        'currencies' => availableCurrencies(),
    ]);
}

if ($method !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

verifyCsrf();
$action = (string) ($_POST['action'] ?? '');

if ($action === 'profile') {
    $result = updateProfileForUser($userId, $_POST, $_FILES);

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to update profile.']), [], 422);
    }

    jsonResponse(true, $result['message'] ?? 'Profile updated successfully.');
}

if ($action === 'security') {
    $result = updatePasswordForUser(
        $userId,
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to update password.']), [], 422);
    }

    jsonResponse(true, $result['message'] ?? 'Password updated');
}

if ($action === 'preferences') {
    $result = updatePreferencesForUser(
        $userId,
        (string) ($_POST['preferred_currency'] ?? 'USD'),
        (string) ($_POST['theme_preference'] ?? 'light')
    );

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to update preferences.']), [], 422);
    }

    jsonResponse(true, $result['message'] ?? 'Preferences saved');
}

if ($action === 'notifications') {
    $result = updateNotificationPreferencesForUser(
        $userId,
        isset($_POST['notify_reminders']),
        isset($_POST['notify_updates'])
    );

    jsonResponse(true, $result['message'] ?? 'Notification preferences saved');
}

if ($action === 'delete_account') {
    $result = deleteAccountForUser(
        $userId,
        (string) ($_POST['delete_confirmation'] ?? ''),
        (string) ($_POST['current_password'] ?? '')
    );

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to delete account.']), [], 422);
    }

    jsonResponse(true, $result['message'] ?? 'Your account was deleted.');
}

jsonResponse(false, 'Unsupported settings action.', [], 400);
