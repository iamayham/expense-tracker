<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

requireLogin();

$summary = getDashboardSummary(currentUserId());
$chartLabels = array_column($summary['chart_data'], 'month_name');
$chartValues = array_map(static fn(array $row): float => (float) $row['total'], $summary['chart_data']);
$pieLabels = array_map(static fn(array $row): string => (string) ($row['category_name'] ?? 'Other'), $summary['category_breakdown']);
$pieValues = array_map(static fn(array $row): float => (float) $row['total'], $summary['category_breakdown']);
$isNegativeBalance = (float) $summary['total_balance'] < 0;

$pageTitle = 'Dashboard';
$pageSubtitle = 'A clear view of where your money moves each day.';
$pageActions = '<div class="topbar-action-group">'
    . '<a class="button button-lg secondary dashboard-cta" href="' . e(url('pages/income.php#income-form')) . '">Add Income</a>'
    . '<a class="button button-lg dashboard-cta" href="' . e(url('pages/expenses.php#expense-form')) . '">Add Expense</a>'
    . '</div>';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero-card card">
    <div>
        <span class="eyebrow">Financial Overview</span>
        <h2>Your finances, simplified.</h2>
        <p class="muted">Track activity, spot trends, and stay ahead of upcoming expenses without the clutter.</p>
    </div>
</section>

<div class="grid grid-3 metrics-grid">
    <section class="card metric-card">
        <div class="metric-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H16a3 3 0 0 1 0 6H6.5A2.5 2.5 0 0 0 4 13.5m0-6v9A2.5 2.5 0 0 0 6.5 19H18a2 2 0 1 0 0-4h-1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div>
            <p class="muted">Total Balance</p>
            <p
                class="metric <?= $isNegativeBalance ? 'negative-balance' : ''; ?>"
                style="<?= $isNegativeBalance ? 'color:#e53935;' : ''; ?>"
            ><?= e(formatCurrency($summary['total_balance'])); ?></p>
            <p class="muted">
                <?= $isNegativeBalance ? '&#9888; Over budget' : 'Updated from income and paid expenses'; ?>
            </p>
        </div>
    </section>
    <section class="card metric-card">
        <div class="metric-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M5 18.5V10m5 8.5V5.5m5 13V13m5 5.5V8.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        </div>
        <div>
            <p class="muted">Monthly Expenses</p>
            <p class="metric"><?= e(formatCurrency($summary['monthly_total'])); ?></p>
            <p class="muted">Current month activity</p>
        </div>
    </section>
    <section class="card metric-card">
        <div class="metric-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div>
            <p class="muted">Upcoming Deductions</p>
            <p class="metric"><?= e(formatCurrency($summary['upcoming_deductions_total'])); ?></p>
            <p class="muted"><?= e((string) $summary['upcoming_deductions_count']); ?> pending item(s)</p>
        </div>
    </section>
</div>

<div class="grid grid-2 section-gap">
    <section class="card chart-card">
        <div class="section-header">
            <div>
                <h2>Expense Breakdown</h2>
                <p class="muted">Top categories by spending</p>
            </div>
        </div>
        <?php if ($pieValues): ?>
            <canvas
                id="expensePieChart"
                data-chart-type="pie"
                data-labels='<?= e(json_encode($pieLabels, JSON_THROW_ON_ERROR)); ?>'
                data-values='<?= e(json_encode($pieValues, JSON_THROW_ON_ERROR)); ?>'
            ></canvas>
        <?php else: ?>
            <div class="chart-empty-state">
                <div class="chart-skeleton"></div>
                <p>No expense breakdown yet. Add a few expenses to populate this chart.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="card chart-card">
        <div class="section-header">
            <div>
                <h2>Monthly Trend</h2>
                <p class="muted">Last six months of expenses</p>
            </div>
        </div>
        <?php if ($chartValues): ?>
            <canvas
                id="expenseLineChart"
                data-chart-type="line"
                data-labels='<?= e(json_encode($chartLabels, JSON_THROW_ON_ERROR)); ?>'
                data-values='<?= e(json_encode($chartValues, JSON_THROW_ON_ERROR)); ?>'
            ></canvas>
        <?php else: ?>
            <div class="chart-empty-state">
                <div class="chart-skeleton"></div>
                <p>No monthly trend yet. Start recording expenses to see your progress over time.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid-2 section-gap">
    <section class="card">
        <div class="section-header">
            <div>
                <h2>Quick Access</h2>
                <p class="muted">Shortcuts to your most-used actions</p>
            </div>
        </div>
        <div class="quick-links">
            <a class="quick-link" href="<?= e(url('pages/expenses.php')); ?>">
                <strong>Expenses</strong>
                <span>Add, edit, and track payment status</span>
            </a>
            <a class="quick-link" href="<?= e(url('pages/categories.php')); ?>">
                <strong>Categories</strong>
                <span>Organize spending with custom labels</span>
            </a>
        </div>
    </section>

    <section class="card">
        <div class="section-header">
            <div>
                <h2>Recent Transactions</h2>
                <p class="muted">Latest recorded expenses</p>
            </div>
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
                <tbody>
                <?php if ($summary['recent_transactions']): ?>
                    <?php foreach ($summary['recent_transactions'] as $transaction): ?>
                        <tr>
                            <td><?= e($transaction['title']); ?></td>
                            <td><?= e($transaction['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><?= e(formatDate($transaction['expense_date'])); ?></td>
                            <td><?= e(formatCurrency((float) $transaction['amount'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="muted">No expenses recorded yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
