<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireGuest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name = normalizeText($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }

    if (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $existsStatement = db()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $existsStatement->execute(['email' => $email]);

        if ((int) $existsStatement->fetchColumn() > 0) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $statement = db()->prepare(
            'INSERT INTO users (name, email, password_hash, preferred_currency, theme_preference, balance, created_at, updated_at)
             VALUES (:name, :email, :password_hash, "USD", "light", 0, NOW(), NOW())'
        );
        $statement->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        setFlash('success', 'Registration successful. You can log in now.');
        redirect('auth/login.php');
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card auth-card">
    <div class="auth-logo-wrap" aria-label="<?= e(appName()); ?>">
        <span class="auth-logo-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M6.5 4.5h8A2.5 2.5 0 0 1 17 7v10.5H6.5A2.5 2.5 0 0 1 4 15V7a2.5 2.5 0 0 1 2.5-2.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M8 9h5.5M8 12h4M8 15h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M18.5 8.5v6M21 10.5v4M16 12v2" stroke="#22c55e" stroke-width="1.8" stroke-linecap="round"/>
                <circle cx="15.5" cy="15.5" r="3.5" fill="#22c55e"/>
                <path d="M15.5 13.9v3.2M16.7 14.8c0-.44-.47-.8-1.05-.8-.58 0-1.05.36-1.05.8s.47.8 1.05.8c.58 0 1.05.36 1.05.8s-.47.8-1.05.8c-.58 0-1.05-.36-1.05-.8" stroke="#ffffff" stroke-width="1.2" stroke-linecap="round"/>
            </svg>
        </span>
        <span class="auth-logo-text">
            <span class="auth-logo-expense">Expense</span><span class="auth-logo-tracker">Tracker</span>
        </span>
    </div>
    <div class="auth-header">
        <div>
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Create your account and start tracking spending with clarity.</p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?= e(implode(' ', $errors)); ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <div class="form-grid">
            <div class="field full">
                <label for="name">Full Name</label>
                <input id="name" type="text" name="name" value="<?= old('name'); ?>" required>
            </div>
            <div class="field full">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= old('email'); ?>" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <input id="confirm_password" type="password" name="confirm_password" required>
            </div>
        </div>
        <p class="auth-link muted">Already registered? <a href="<?= e(url('auth/login.php')); ?>">Log in here</a>.</p>
        <button class="auth-submit" type="submit">Register</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
