<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = currentUserId();
$editingIncome = null;
$errors = [];

if (isset($_GET['edit'])) {
    $editingIncome = getIncomeById($userId, (int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $result = deleteIncomeForUser($userId, (int) ($_POST['income_id'] ?? 0));
        setFlash($result['success'] ? 'success' : 'error', $result['message'] ?? implode(' ', $result['errors'] ?? ['Unable to delete income.']));

        redirect('pages/income.php');
    }

    $incomeId = (int) ($_POST['income_id'] ?? 0);
    $result = saveIncomeForUser($userId, $_POST, $incomeId);

    if ($result['success']) {
        setFlash('success', $result['message'] ?? 'Income saved successfully.');
        redirect('pages/income.php');
    }

    $errors = $result['errors'] ?? [];
    $editingIncome = array_merge(['id' => $incomeId], $result['data'] ?? []);
}

$filterFrom = (string) ($_GET['from_date'] ?? '');
$filterTo = (string) ($_GET['to_date'] ?? '');
$incomeEntries = listIncomeForUser($userId, [
    'from_date' => $filterFrom,
    'to_date' => $filterTo,
]);
$incomeSummary = getIncomeSummaryForUser($userId);
$currentMonthLabel = date('F');
$latestIncomeLabel = !empty($incomeSummary['latest_income_date'])
    ? formatDate((string) $incomeSummary['latest_income_date'])
    : 'No income yet';

$pageTitle = 'Income';
$pageSubtitle = 'Record salary, side income, and other money coming in';
$pageActions = '<div class="topbar-action-group">'
    . '<a class="button button-lg secondary" href="' . e(url('pages/expenses.php#expense-form')) . '">Add Expense</a>'
    . '<a class="button button-lg" href="#income-form">Add Income</a>'
    . '</div>';
require_once __DIR__ . '/../includes/header.php';
?>
<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div class="grid grid-3 metrics-grid">
    <section class="card metric-card expense-summary-card">
        <div class="metric-icon">T</div>
        <div>
            <p class="muted">Total Income</p>
            <p class="metric"><?= e(formatCurrency((float) $incomeSummary['total_income'])); ?></p>
            <p class="muted">All income recorded</p>
        </div>
    </section>
    <section class="card metric-card expense-summary-card">
        <div class="metric-icon">M</div>
        <div>
            <p class="muted"><?= e($currentMonthLabel); ?></p>
            <p class="metric"><?= e(formatCurrency((float) $incomeSummary['monthly_income'])); ?></p>
            <p class="muted">Current month income</p>
        </div>
    </section>
    <section class="card metric-card expense-summary-card">
        <div class="metric-icon">L</div>
        <div>
            <p class="muted">Latest Income</p>
            <p class="metric" style="font-size: 1.3rem;"><?= e($latestIncomeLabel); ?></p>
            <p class="muted">Most recent entry date</p>
        </div>
    </section>
</div>

<div class="expenses-layout section-gap">
    <section class="card expense-form-card">
        <div id="income-form"></div>
        <div class="card-header-block">
            <h2><?= $editingIncome ? 'Edit Income' : 'Add Income'; ?></h2>
            <p class="muted">Capture money coming in so your balance stays accurate.</p>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="income_id" value="<?= e((string) ($editingIncome['id'] ?? '0')); ?>">
            <div class="form-grid">
                <div class="field full">
                    <label for="title">Title</label>
                    <input id="title" type="text" name="title" value="<?= e($editingIncome['title'] ?? ''); ?>" required>
                </div>
                <div class="field">
                    <label for="source">Source</label>
                    <input id="source" type="text" name="source" value="<?= e($editingIncome['source'] ?? ''); ?>" placeholder="Employer, client, refund...">
                </div>
                <div class="field">
                    <label for="amount">Amount</label>
                    <input id="amount" type="number" step="0.01" min="0.01" name="amount" value="<?= e((string) ($editingIncome['amount'] ?? '')); ?>" required>
                </div>
                <div class="field">
                    <label for="income_date">Date</label>
                    <input id="income_date" type="date" name="income_date" value="<?= e($editingIncome['income_date'] ?? date('Y-m-d')); ?>" required>
                </div>
            </div>
            <div class="actions" style="margin-top: 1rem;">
                <button class="button-lg" type="submit" data-loading-button data-loading-text="<?= e($editingIncome ? 'Updating...' : 'Adding...'); ?>"><?= $editingIncome ? 'Update Income' : 'Add Income'; ?></button>
                <?php if ($editingIncome): ?>
                    <a class="button secondary" href="<?= e(url('pages/income.php')); ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card filter-card">
        <div class="card-header-block">
            <h2>Filter Income</h2>
            <p class="muted">Focus on a specific date range</p>
        </div>
        <form method="get">
            <div class="form-grid">
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
                <a class="button secondary" href="<?= e(url('pages/income.php')); ?>">Reset</a>
            </div>
        </form>
    </section>
</div>

<section class="card section-gap table-card">
    <div class="card-header-block">
        <h2>Income History</h2>
        <p class="muted">Every income entry in one place.</p>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Title</th>
                <th>Source</th>
                <th>Date</th>
                <th>Amount</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($incomeEntries): ?>
                <?php foreach ($incomeEntries as $income): ?>
                    <tr>
                        <td><strong><?= e($income['title']); ?></strong></td>
                        <td><?= e($income['source'] ?: 'Direct'); ?></td>
                        <td><?= e(formatDate((string) $income['income_date'])); ?></td>
                        <td class="amount-positive">
                            <?= e(formatCurrency((float) $income['amount'])); ?>
                            <div class="table-actions-inline">
                                <a class="button secondary" href="<?= e(url('pages/income.php?edit=' . (string) $income['id'])); ?>">Edit</a>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="income_id" value="<?= e((string) $income['id']); ?>">
                                    <button class="danger" type="submit" data-loading-button data-loading-text="Deleting...">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="muted">No income found for the selected filters.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
