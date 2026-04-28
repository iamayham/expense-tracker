<?php
declare(strict_types=1);

$flash = $flash ?? getFlash();
$pageTitle = $pageTitle ?? appName();
$pageSubtitle = $pageSubtitle ?? '';
$loggedIn = isLoggedIn();
$pageActions = $pageActions ?? '';
$useToastFlash = $useToastFlash ?? false;
$bodyThemeClass = $loggedIn ? 'theme-' . currentUserThemePreference() : 'theme-light';
$bodyCurrency = $loggedIn ? currentUserCurrency() : 'USD';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')); ?>">
</head>
<body class="<?= e($bodyThemeClass); ?>" data-user-currency="<?= e($bodyCurrency); ?>">
<div class="app-shell">
    <?php if ($loggedIn): ?>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <a class="brand" href="<?= e(url('index.php')); ?>">
                    <span class="brand-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6.5 4.5h8A2.5 2.5 0 0 1 17 7v10.5H6.5A2.5 2.5 0 0 1 4 15V7a2.5 2.5 0 0 1 2.5-2.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M8 9h5.5M8 12h4M8 15h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M18.5 8.5v6M21 10.5v4M16 12v2" stroke="#22c55e" stroke-width="1.8" stroke-linecap="round"/>
                            <circle cx="15.5" cy="15.5" r="3.5" fill="#22c55e"/>
                            <path d="M15.5 13.9v3.2M16.7 14.8c0-.44-.47-.8-1.05-.8-.58 0-1.05.36-1.05.8s.47.8 1.05.8c.58 0 1.05.36 1.05.8s-.47.8-1.05.8c-.58 0-1.05-.36-1.05-.8" stroke="#ffffff" stroke-width="1.2" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="brand-text">
                        <span class="brand-expense">Expense</span><span class="brand-tracker">Tracker</span>
                    </span>
                </a>
                <p class="subtitle">Simple, modern expense control.</p>
            </div>

            <nav class="sidebar-nav">
                <a class="nav-item <?= routeIs('index.php') ? 'active' : ''; ?>" href="<?= e(url('index.php')); ?>">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4 13.5h6.5V20H4v-6.5Zm9.5-9.5H20V20h-6.5V4ZM4 4h6.5v6.5H4V4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Dashboard</span>
                </a>
                <a class="nav-item <?= routeIs('pages/expenses.php') ? 'active' : ''; ?>" href="<?= e(url('pages/expenses.php')); ?>">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 4h10l3 3v13H4V4h3Zm0 0v4h10V4M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Expenses</span>
                </a>
                <a class="nav-item <?= routeIs('pages/income.php') ? 'active' : ''; ?>" href="<?= e(url('pages/income.php')); ?>">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 19V5m0 0-4 4m4-4 4 4M5 19h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Income</span>
                </a>
                <span class="nav-item disabled">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 7.5A2.5 2.5 0 0 1 8.5 5H18v12.5A2.5 2.5 0 0 0 15.5 15H6V7.5Zm0 0A2.5 2.5 0 0 0 3.5 10v7.5A2.5 2.5 0 0 1 6 15m4-5h5m-5 3h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Subscriptions</span>
                    <small>Soon</small>
                </span>
                <a class="nav-item <?= routeIs('pages/categories.php') ? 'active' : ''; ?>" href="<?= e(url('pages/categories.php')); ?>">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4 8.5A2.5 2.5 0 0 1 6.5 6H12l8 8-6 6-8-8V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <circle cx="8.5" cy="9.5" r="1" fill="currentColor"/>
                        </svg>
                    </span>
                    <span>Categories</span>
                </a>
                <a class="nav-item <?= routeIs('pages/settings.php') ? 'active' : ''; ?>" href="<?= e(url('pages/settings.php')); ?>">
                    <span class="nav-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 4v3m0 10v3m8-8h-3M7 12H4m13.657 5.657-2.121-2.121M8.464 8.464 6.343 6.343m11.314 0-2.121 2.121M8.464 15.536l-2.121 2.121M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Settings</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a class="button secondary sidebar-logout" href="<?= e(url('auth/logout.php')); ?>">Logout</a>
            </div>
        </aside>
        <button class="sidebar-overlay" type="button" id="sidebarOverlay" aria-label="Close menu"></button>
    <?php endif; ?>

    <div class="main-panel">
        <?php if ($loggedIn): ?>
            <header class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" type="button" id="menuToggle" aria-label="Toggle menu">☰</button>
                    <div>
                        <p class="eyebrow">Expense Tracker</p>
                        <h1 class="topbar-title"><?= e($pageTitle); ?></h1>
                        <?php if ($pageSubtitle !== ''): ?>
                            <p class="topbar-subtitle"><?= e($pageSubtitle); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="topbar-actions">
                    <?php if ($pageActions !== ''): ?>
                        <?= $pageActions; ?>
                    <?php endif; ?>
                    <button class="icon-button" type="button" aria-label="Notifications">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M14.857 17H9.143m5.714 0H18l-1.714-2.286V10.143a4.286 4.286 0 1 0-8.572 0v4.571L6 17h3.143m5.714 0a2.857 2.857 0 1 1-5.714 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="profile-chip">
                        <span class="profile-avatar"><?= e(strtoupper(substr(currentUserName(), 0, 1))); ?></span>
                        <div>
                            <strong><?= e(currentUserName()); ?></strong>
                            <small>Personal</small>
                        </div>
                    </div>
                </div>
            </header>
        <?php endif; ?>

        <main class="container <?= $loggedIn ? '' : 'guest-container'; ?>">
        <?php if ($flash): ?>
            <?php if ($useToastFlash): ?>
                <div class="toast-trigger" data-toast-message="<?= e($flash['message']); ?>" data-toast-type="<?= e($flash['type']); ?>" hidden></div>
                <div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="true"></div>
            <?php else: ?>
                <div class="alert alert-<?= e($flash['type']); ?>">
                    <?= e($flash['message']); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
