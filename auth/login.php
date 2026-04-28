<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireGuest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $rememberMe = ($_POST['remember_me'] ?? '') === '1';

    if (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {
        $statement = db()->prepare('SELECT id, name, email, avatar_url, password_hash, preferred_currency, theme_preference FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            completeUserLogin($user, $rememberMe);
            setFlash('success', 'Welcome back, ' . $user['name'] . '.');
            redirect('');
        }
    }
}

$pageTitle = 'Login';
$showInstallBanner = true;
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
            <p class="auth-welcome">Welcome back &#128075;</p>
            <h1 class="auth-title">Login</h1>
            <p class="auth-subtitle">Access your modern expense dashboard and stay in control.</p>
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
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="<?= old('email'); ?>" required>
            </div>
            <div class="field full">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div class="field full">
                <label class="remember-checkbox" for="remember_me">
                    <input class="remember-checkbox-input" id="remember_me" type="checkbox" name="remember_me" value="1" <?= (($_POST['remember_me'] ?? '') === '1') ? 'checked' : ''; ?>>
                    <span>Remember me</span>
                </label>
            </div>
        </div>
        <p class="auth-link muted"><a href="<?= e(url('auth/forgot-password.php')); ?>">Forgot password?</a></p>
        <p class="auth-link muted">No account yet? <a href="<?= e(url('auth/register.php')); ?>">Create one</a>.</p>
        <button class="auth-submit" type="submit">Login</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
