<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = currentUserId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    jsonResponse(true, 'Expenses loaded successfully.', [
        'expenses' => listExpensesForUser($userId, $_GET),
        'summary' => getExpenseSummaryForUser($userId),
    ]);
}

if ($method !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

verifyCsrf();
$action = (string) ($_POST['action'] ?? 'save');

if ($action === 'save') {
    $result = saveExpenseForUser($userId, $_POST, (int) ($_POST['expense_id'] ?? 0));

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to save expense.']), $result['data'] ?? [], 422);
    }

    jsonResponse(true, $result['message'] ?? 'Expense saved successfully.', $result['data'] ?? []);
}

if ($action === 'toggle_status') {
    $result = toggleExpenseStatusForUser($userId, (int) ($_POST['expense_id'] ?? 0));

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to update expense.']), [], 404);
    }

    jsonResponse(true, $result['message'] ?? 'Payment status updated successfully.', [
        'status' => $result['status'] ?? null,
    ]);
}

if ($action === 'delete') {
    $result = deleteExpenseForUser($userId, (int) ($_POST['expense_id'] ?? 0));

    if (!$result['success']) {
        jsonResponse(false, implode(' ', $result['errors'] ?? ['Unable to delete expense.']), [], 404);
    }

    jsonResponse(true, $result['message'] ?? 'Expense deleted successfully.');
}

jsonResponse(false, 'Unsupported expenses action.', [], 400);
