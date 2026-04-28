<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = currentUserId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    jsonResponse(true, 'Categories loaded successfully.', [
        'categories' => getUserCategories($userId),
    ]);
}

if ($method !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

verifyCsrf();
$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'save') {
    $result = saveCategoryForUser($userId, (string) ($_POST['name'] ?? ''), (int) ($_POST['category_id'] ?? 0));

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to save category.']), [], 422);
    }

    jsonResponse(true, $result['message'] ?? 'Category saved successfully.');
}

if ($action === 'delete') {
    $result = deleteCategoryForUser($userId, (int) ($_POST['category_id'] ?? 0));

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to delete category.']), [], 422);
    }

    jsonResponse(true, $result['message'] ?? 'Category deleted successfully.');
}

jsonResponse(false, 'Unsupported categories action.', [], 400);
