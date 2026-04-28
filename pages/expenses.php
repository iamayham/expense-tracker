<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = currentUserId();
$categories = getUserCategories($userId);
$editingExpense = null;
$errors = [];

if (isset($_GET['edit'])) {
    $editingExpense = getExpenseById($userId, (int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'toggle_status') {
        $result = toggleExpenseStatusForUser($userId, (int) ($_POST['expense_id'] ?? 0));
        setFlash($result['success'] ? 'success' : 'error', $result['message'] ?? implode(' ', $result['errors'] ?? ['Unable to update expense.']));

        redirect('pages/expenses.php');
    }

    if ($action === 'delete') {
        $result = deleteExpenseForUser($userId, (int) ($_POST['expense_id'] ?? 0));
        setFlash($result['success'] ? 'success' : 'error', $result['message'] ?? implode(' ', $result['errors'] ?? ['Unable to delete expense.']));

        redirect('pages/expenses.php');
    }

    $expenseId = (int) ($_POST['expense_id'] ?? 0);
    $result = saveExpenseForUser($userId, $_POST, $expenseId);

    if ($result['success']) {
        setFlash('success', $result['message'] ?? 'Expense saved successfully.');
        redirect('pages/expenses.php');
    }

    $errors = $result['errors'] ?? [];
    $editingExpense = array_merge(['id' => $expenseId], $result['data'] ?? []);
}

$filterCategory = (int) ($_GET['category_id'] ?? 0);
$filterFrom = $_GET['from_date'] ?? '';
$filterTo = $_GET['to_date'] ?? '';
$expenses = listExpensesForUser($userId, [
    'category_id' => $filterCategory,
    'from_date' => $filterFrom,
    'to_date' => $filterTo,
]);
$expenseSummary = getExpenseSummaryForUser($userId);
$currentMonthLabel = date('F');

$pageTitle = 'Expenses';
$pageSubtitle = 'Track, filter, and manage your spending';
$pageActions = '<div class="topbar-action-group">'
    . '<a class="button button-lg secondary" href="' . e(url('pages/income.php#income-form')) . '">Add Income</a>'
    . '<a class="button button-lg" href="#expense-form">Add Expense</a>'
    . '</div>';
require_once __DIR__ . '/../includes/header.php';
?>
<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div
    data-expenses-page
    data-api-url="<?= e(url('api/expenses.php')); ?>"
    data-base-edit-url="<?= e(url('pages/expenses.php?edit=')); ?>"
>
<div class="grid grid-3 metrics-grid">
    <section class="card metric-card expense-summary-card">
        <div class="metric-icon">T</div>
        <div>
            <p class="muted">Total Expenses</p>
            <p class="metric" data-expense-summary="total"><?= e(formatCurrency((float) $expenseSummary['total_expenses'])); ?></p>
            <p class="muted">All spending recorded</p>
        </div>
    </section>
    <section class="card metric-card expense-summary-card">
        <div class="metric-icon">M</div>
        <div>
            <p class="muted"><?= e($currentMonthLabel); ?></p>
            <p class="metric" data-expense-summary="monthly"><?= e(formatCurrency((float) $expenseSummary['monthly_expenses'])); ?></p>
            <p class="muted">Current month expenses</p>
        </div>
    </section>
    <section class="card metric-card expense-summary-card">
        <div class="metric-icon">U</div>
        <div>
            <p class="muted">Upcoming Deductions</p>
            <p class="metric" data-expense-summary="upcoming"><?= e(formatCurrency((float) $expenseSummary['upcoming_deductions'])); ?></p>
            <p class="muted">Pending items</p>
        </div>
    </section>
</div>

<div class="expenses-layout section-gap">
    <section class="card expense-form-card">
        <div id="expense-form"></div>
        <div class="card-header-block">
            <h2><?= $editingExpense ? 'Edit Expense' : 'Add Expense'; ?></h2>
            <p class="muted">Capture spending details quickly and clearly.</p>
        </div>
        <form method="post" data-expense-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="expense_id" value="<?= e((string) ($editingExpense['id'] ?? '0')); ?>" data-expense-id>
            <div class="form-grid">
                <div class="field full">
                    <label for="title">Title</label>
                    <input id="title" type="text" name="title" value="<?= e($editingExpense['title'] ?? ''); ?>" required>
                </div>
                <div class="field">
                    <label for="amount">Amount</label>
                    <input id="amount" type="number" step="0.01" min="0.01" name="amount" value="<?= e((string) ($editingExpense['amount'] ?? '')); ?>" required>
                </div>
                <div class="field">
                    <label for="expense_date">Date</label>
                    <input id="expense_date" type="date" name="expense_date" value="<?= e($editingExpense['expense_date'] ?? date('Y-m-d')); ?>" required>
                </div>
                <div class="field">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']); ?>" <?= (int) ($editingExpense['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?= e($category['name']); ?><?= (int) $category['is_default'] === 1 ? ' (Default)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="payment_status">Payment Status</label>
                    <div class="radio-group">
                        <label class="radio-pill">
                            <input type="radio" name="payment_status" value="paid" <?= ($editingExpense['payment_status'] ?? 'pending') === 'paid' ? 'checked' : ''; ?>>
                            <span>Paid</span>
                        </label>
                        <label class="radio-pill">
                            <input type="radio" name="payment_status" value="pending" <?= ($editingExpense['payment_status'] ?? 'pending') === 'pending' ? 'checked' : ''; ?>>
                            <span>Not Paid</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="actions" style="margin-top: 1rem;">
                <button class="button-lg" type="submit" data-loading-button data-loading-text="<?= e($editingExpense ? 'Updating...' : 'Adding...'); ?>"><?= $editingExpense ? 'Update Expense' : 'Add Expense'; ?></button>
                <?php if ($editingExpense): ?>
                    <a class="button secondary" href="<?= e(url('pages/expenses.php')); ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card filter-card">
        <div class="card-header-block">
            <h2>Filter Expenses</h2>
            <p class="muted">Refine your expense list</p>
        </div>
        <form method="get" data-expense-filter-form>
            <div class="form-grid">
                <div class="field full">
                    <label for="filter_category_id">Category</label>
                    <select id="filter_category_id" name="category_id">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']); ?>" <?= $filterCategory === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?= e($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="from_date">From</label>
                    <input id="from_date" type="date" name="from_date" value="<?= e($filterFrom); ?>">
                </div>
                <div class="field">
                    <label for="to_date">To</label>
                    <input id="to_date" type="date" name="to_date" value="<?= e($filterTo); ?>">
                </div>
            </div>
            <div class="actions" style="margin-top: 1rem;">
                <button type="submit" data-loading-button data-loading-text="Applying...">Apply Filters</button>
                <a class="button secondary" href="<?= e(url('pages/expenses.php')); ?>">Reset</a>
            </div>
        </form>
    </section>
</div>

<section class="card section-gap table-card">
    <div class="card-header-block">
        <h2>Expense History</h2>
        <p class="muted">Every transaction in one place.</p>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Title</th>
                <th>Category</th>
                <th>Date</th>
                <th>Amount</th>
            </tr>
            </thead>
            <tbody data-expenses-tbody>
            <?php if ($expenses): ?>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td>
                            <strong><?= e($expense['title']); ?></strong>
                            <div class="table-meta">
                                <?php if (($expense['payment_status'] ?? 'pending') === 'paid'): ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Not Paid</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= e($expense['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?= e(formatDate($expense['expense_date'])); ?></td>
                        <td class="<?= ($expense['payment_status'] ?? 'pending') === 'paid' ? 'amount-positive' : 'amount-negative'; ?>">
                            <?= e(formatCurrency((float) $expense['amount'])); ?>
                            <div class="table-actions-inline">
                                <a class="button secondary" href="<?= e(url('pages/expenses.php?edit=' . (string) $expense['id'])); ?>">Edit</a>
                                <form method="post" class="inline-form" data-expense-delete-form>
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="expense_id" value="<?= e((string) $expense['id']); ?>">
                                    <button class="danger" type="submit" data-loading-button data-loading-text="Deleting...">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="muted">No expenses found for the selected filters.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
