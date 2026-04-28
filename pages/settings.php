<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = currentUserId();
$errors = [];
$flash = getFlash();
$currencies = availableCurrencies();
$user = getCurrentUser($userId);

if (!$user) {
    setFlash('error', 'User profile could not be loaded.');
    redirect('auth/login.php');
}

$profileData = [
    'name' => (string) $user['name'],
    'email' => (string) $user['email'],
    'avatar_url' => (string) ($user['avatar_url'] ?? ''),
    'avatar_mode' => ((string) ($user['avatar_url'] ?? '')) !== '' ? 'uploaded' : 'generated',
];

$preferencesData = [
    'preferred_currency' => (string) ($user['preferred_currency'] ?? 'USD'),
    'theme_preference' => (string) ($user['theme_preference'] ?? 'light'),
    'notify_reminders' => (int) ($user['notify_reminders'] ?? 1),
    'notify_updates' => (int) ($user['notify_updates'] ?? 1),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $profileData = [
            'name' => normalizeText($_POST['name'] ?? ''),
            'email' => strtolower(trim($_POST['email'] ?? '')),
            'avatar_url' => trim($_POST['avatar_url'] ?? ''),
            'avatar_mode' => $_POST['avatar_mode'] ?? 'generated',
        ];
        $result = updateProfileForUser($userId, $_POST, $_FILES);

        if ($result['success']) {
            setFlash('success', $result['message'] ?? 'Profile updated successfully');
            redirect('pages/settings.php');
        }

        $errors = $result['errors'] ?? [];
    }

    if ($action === 'security') {
        $result = updatePasswordForUser(
            $userId,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );

        if ($result['success']) {
            setFlash('success', $result['message'] ?? 'Password updated');
            redirect('pages/settings.php');
        }

        $errors = $result['errors'] ?? [];
    }

    if ($action === 'preferences') {
        $preferencesData['preferred_currency'] = strtoupper(trim($_POST['preferred_currency'] ?? 'USD'));
        $preferencesData['theme_preference'] = (string) ($_POST['theme_preference'] ?? 'light');
        $result = updatePreferencesForUser(
            $userId,
            $preferencesData['preferred_currency'],
            $preferencesData['theme_preference']
        );

        if ($result['success']) {
            setFlash('success', $result['message'] ?? 'Preferences saved');
            redirect('pages/settings.php');
        }

        $errors = $result['errors'] ?? [];
    }

    if ($action === 'notifications') {
        $notifyReminders = isset($_POST['notify_reminders']) ? 1 : 0;
        $notifyUpdates = isset($_POST['notify_updates']) ? 1 : 0;

        $preferencesData['notify_reminders'] = $notifyReminders;
        $preferencesData['notify_updates'] = $notifyUpdates;

        $result = updateNotificationPreferencesForUser($userId, $notifyReminders === 1, $notifyUpdates === 1);
        setFlash($result['success'] ? 'success' : 'error', $result['message'] ?? implode(' ', $result['errors'] ?? ['Unable to save notifications.']));
        redirect('pages/settings.php');
    }

    if ($action === 'delete_account') {
        $result = deleteAccountForUser(
            $userId,
            (string) ($_POST['delete_confirmation'] ?? ''),
            (string) ($_POST['current_password'] ?? '')
        );

        if ($result['success']) {
            redirect('auth/register.php');
        }

        $errors = $result['errors'] ?? [];
    }
}

$pageTitle = 'Settings';
$pageSubtitle = 'Manage your profile, preferences, and account security';
$useToastFlash = true;
require_once __DIR__ . '/../includes/header.php';
?>
<?php if (!$flash): ?>
    <div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="true"></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="toast-trigger" data-toast-message="<?= e(implode(' ', $errors)); ?>" data-toast-type="error" hidden></div>
<?php endif; ?>

<div class="settings-grid section-gap">
    <section class="card settings-card settings-card-wide">
        <div class="settings-card-header">
            <div>
                <p class="eyebrow settings-eyebrow">Profile Settings</p>
                <h2>Personal Information</h2>
                <p class="muted">Keep your account details current and personalize your profile.</p>
            </div>
            <div class="settings-avatar-preview">
                <?php if (($profileData['avatar_url'] ?? '') !== ''): ?>
                    <img src="<?= e($profileData['avatar_url']); ?>" alt="<?= e($profileData['name']); ?>">
                <?php else: ?>
                    <span><?= e(strtoupper(substr($profileData['name'], 0, 1))); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="settings-form" data-settings-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="profile">
            <input type="hidden" name="avatar_mode" id="avatarMode" value="<?= e($profileData['avatar_mode']); ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="settings-name">Name</label>
                    <input id="settings-name" type="text" name="name" value="<?= e($profileData['name']); ?>" required>
                </div>
                <div class="field">
                    <label for="settings-email">Email</label>
                    <input id="settings-email" type="email" name="email" value="<?= e($profileData['email']); ?>" required>
                </div>
                <div class="field full">
                    <label>Avatar</label>
                    <div class="avatar-choice-row">
                        <button class="button secondary avatar-choice-button <?= $profileData['avatar_mode'] === 'generated' ? 'is-active' : ''; ?>" type="button" data-avatar-choice="generated">Auto-generated</button>
                        <button class="button secondary avatar-choice-button <?= $profileData['avatar_mode'] === 'uploaded' ? 'is-active' : ''; ?>" type="button" data-avatar-choice="uploaded">Upload / URL</button>
                    </div>
                    <div class="avatar-upload-panel <?= $profileData['avatar_mode'] === 'uploaded' ? 'is-visible' : ''; ?>" id="avatarUploadPanel">
                        <input id="settings-avatar-url" type="url" name="avatar_url" value="<?= e($profileData['avatar_url']); ?>" placeholder="https://example.com/avatar.jpg">
                        <input id="settings-avatar-file" type="file" name="avatar_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    </div>
                    <small class="field-note">Leave it on auto-generated to use initials, or upload an image up to 2MB.</small>
                </div>
            </div>

            <div class="settings-actions">
                <button class="button" type="submit" data-loading-button data-loading-text="Saving...">Save Profile</button>
            </div>
        </form>
    </section>

    <section class="card settings-card">
        <div class="settings-card-header">
            <div>
                <p class="eyebrow settings-eyebrow">Security</p>
                <h2>Change Password</h2>
                <p class="muted">Use a strong password to keep your account protected.</p>
            </div>
        </div>

        <form method="post" class="settings-form" data-settings-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="security">

            <div class="form-grid">
                <div class="field full">
                    <label for="settings-password">New Password</label>
                    <div class="password-field">
                        <input id="settings-password" type="password" name="password" minlength="8" required data-password-input>
                        <button class="password-toggle" type="button" data-password-toggle>Show</button>
                    </div>
                    <small class="field-note">Use at least 8 characters.</small>
                </div>
                <div class="field full">
                    <label for="settings-confirm-password">Confirm Password</label>
                    <div class="password-field">
                        <input id="settings-confirm-password" type="password" name="confirm_password" minlength="8" required data-password-input>
                        <button class="password-toggle" type="button" data-password-toggle>Show</button>
                    </div>
                </div>
            </div>

            <div class="settings-actions">
                <button class="button" type="submit" data-loading-button data-loading-text="Updating...">Update Password</button>
            </div>
        </form>
    </section>

    <section class="card settings-card">
        <div class="settings-card-header">
            <div>
                <p class="eyebrow settings-eyebrow">Preferences</p>
                <h2>Workspace Preferences</h2>
                <p class="muted">Choose how your dashboard feels and displays money.</p>
            </div>
        </div>

        <form method="post" class="settings-form" data-settings-form data-theme-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="preferences">

            <div class="form-grid">
                <div class="field">
                    <label for="settings-currency">Currency</label>
                    <select id="settings-currency" name="preferred_currency">
                        <?php foreach ($currencies as $code => $label): ?>
                            <option value="<?= e($code); ?>" <?= $preferencesData['preferred_currency'] === $code ? 'selected' : ''; ?>>
                                <?= e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Theme</label>
                    <div class="radio-group">
                        <label class="radio-pill">
                            <input type="radio" name="theme_preference" value="light" <?= $preferencesData['theme_preference'] === 'light' ? 'checked' : ''; ?> data-theme-option>
                            <span>Light</span>
                        </label>
                        <label class="radio-pill">
                            <input type="radio" name="theme_preference" value="dark" <?= $preferencesData['theme_preference'] === 'dark' ? 'checked' : ''; ?> data-theme-option>
                            <span>Dark</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="settings-actions">
                <button class="button" type="submit" data-loading-button data-loading-text="Saving...">Save Preferences</button>
            </div>
        </form>
    </section>

    <section class="card settings-card">
        <div class="settings-card-header">
            <div>
                <p class="eyebrow settings-eyebrow">Notifications</p>
                <h2>Stay Updated</h2>
                <p class="muted">Choose which reminders and product updates you want to receive.</p>
            </div>
        </div>

        <form method="post" class="settings-form" data-settings-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="notifications">

            <div class="settings-switch-list">
                <label class="settings-switch-row">
                    <span>
                        <strong>Payment reminders</strong>
                        <small class="muted">Get nudges for pending or upcoming expenses.</small>
                    </span>
                    <span class="switch">
                        <input type="checkbox" name="notify_reminders" <?= (int) $preferencesData['notify_reminders'] === 1 ? 'checked' : ''; ?>>
                        <span class="switch-slider"></span>
                    </span>
                </label>

                <label class="settings-switch-row">
                    <span>
                        <strong>Product updates</strong>
                        <small class="muted">Receive tips and feature announcements.</small>
                    </span>
                    <span class="switch">
                        <input type="checkbox" name="notify_updates" <?= (int) $preferencesData['notify_updates'] === 1 ? 'checked' : ''; ?>>
                        <span class="switch-slider"></span>
                    </span>
                </label>
            </div>

            <div class="settings-actions">
                <button class="button" type="submit" data-loading-button data-loading-text="Saving...">Save Notifications</button>
            </div>
        </form>
    </section>

    <section class="card settings-card danger-zone-card settings-card-wide">
        <div class="settings-card-header">
            <div>
                <p class="eyebrow settings-eyebrow settings-eyebrow-danger">Danger Zone</p>
                <h2>Delete Account</h2>
                <p class="muted">This permanently removes your account, categories, and expenses.</p>
            </div>
        </div>

        <form method="post" class="settings-form" data-settings-form id="deleteAccountForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="delete_account">

            <div class="form-grid">
                <div class="field full">
                    <label for="delete-confirmation">Type DELETE to confirm</label>
                    <input id="delete-confirmation" type="text" name="delete_confirmation" placeholder="DELETE">
                </div>
                <div class="field full">
                    <label for="delete-password">Current Password</label>
                    <div class="password-field">
                        <input id="delete-password" type="password" name="current_password" required data-password-input>
                        <button class="password-toggle" type="button" data-password-toggle>Show</button>
                    </div>
                </div>
            </div>

            <div class="settings-danger-banner">
                <strong>Warning:</strong> This action cannot be undone.
            </div>

            <div class="settings-actions">
                <button class="button danger" type="button" id="openDeleteAccountModal">Delete Account</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" id="deleteAccountModal" hidden>
    <div class="modal-card modal-card-sm" role="dialog" aria-modal="true" aria-labelledby="deleteAccountModalTitle">
        <div class="modal-header">
            <div>
                <h3 id="deleteAccountModalTitle">Delete account?</h3>
                <p class="muted">This will permanently remove your profile, categories, and expenses.</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Close delete account modal">×</button>
        </div>
        <div class="settings-danger-banner">
            Type <strong>DELETE</strong> and enter your current password before continuing.
        </div>
        <div class="modal-actions">
            <button class="button secondary" type="button" data-modal-close>Cancel</button>
            <button class="button danger" type="button" id="confirmDeleteAccount" data-loading-button data-loading-text="Deleting...">Yes, Delete Account</button>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
