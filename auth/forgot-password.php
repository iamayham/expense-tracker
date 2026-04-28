<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireGuest();

$errors = [];
$verifiedEmail = '';

if (isset($_SESSION['password_reset_email']) && validateEmail((string) $_SESSION['password_reset_email'])) {
    $verifiedEmail = (string) $_SESSION['password_reset_email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $action = (string) ($_POST['action'] ?? 'verify');

    if (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        $statement = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $userId = (int) $statement->fetchColumn();

        if ($userId <= 0) {
            unset($_SESSION['password_reset_email']);
            $errors[] = 'No account was found for that email address.';
        } elseif ($action === 'verify') {
            $_SESSION['password_reset_email'] = $email;
            $verifiedEmail = $email;
        } else {
            if ($verifiedEmail === '' || $verifiedEmail !== $email) {
                $errors[] = 'Please verify your email before resetting the password.';
            }

            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }

            if ($password !== $confirmPassword) {
                $errors[] = 'Password confirmation does not match.';
            }
        }

        if (!$errors && $action === 'reset') {
            $update = db()->prepare(
                'UPDATE users
                 SET password_hash = :password_hash, updated_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $userId,
            ]);

            unset($_SESSION['password_reset_email']);
            setFlash('success', 'Password reset successful. You can log in now.');
            redirect('auth/login.php');
        }
    }
}

$pageTitle = 'Forgot Password';
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
            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Enter your account email and choose a new password to regain access.</p>
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
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?= e($verifiedEmail !== '' ? $verifiedEmail : old('email')); ?>"
                    <?= $verifiedEmail !== '' ? 'readonly' : '' ?>
                    required
                >
            </div>
            <?php if ($verifiedEmail !== ''): ?>
                <div class="field full">
                    <label for="password">New Password</label>
                    <input id="password" type="password" name="password" required>
                </div>
                <div class="field full">
                    <label for="confirm_password">Confirm New Password</label>
                    <input id="confirm_password" type="password" name="confirm_password" required>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($verifiedEmail !== ''): ?>
            <input type="hidden" name="action" value="reset">
        <?php else: ?>
            <input type="hidden" name="action" value="verify">
        <?php endif; ?>
        <p class="auth-link muted"><a href="<?= e(url('auth/login.php')); ?>">Back to login</a></p>
        <button class="auth-submit" type="submit"><?= $verifiedEmail !== '' ? 'Reset Password' : 'Verify Account'; ?></button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
