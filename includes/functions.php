<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['remember_login_checked'])) {
    $_SESSION['remember_login_checked'] = 1;
    tryLoginFromRememberToken();
}

function db(): PDO
{
    global $pdo;

    return $pdo;
}

function appName(): string
{
    return 'Expense Tracker';
}

function currentUserName(): string
{
    return (string) ($_SESSION['user_name'] ?? 'Guest');
}

function currentUserEmail(): string
{
    return (string) ($_SESSION['user_email'] ?? '');
}

function currentUserAvatarUrl(): string
{
    $avatarUrl = trim((string) ($_SESSION['user_avatar_url'] ?? ''));

    if ($avatarUrl !== '') {
        return $avatarUrl;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        return '';
    }

    try {
        $statement = db()->prepare('SELECT avatar_url FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $avatarUrl = trim((string) $statement->fetchColumn());
    } catch (Throwable $exception) {
        return '';
    }

    $_SESSION['user_avatar_url'] = $avatarUrl;

    return $avatarUrl;
}

function currentUserThemePreference(): string
{
    $theme = (string) ($_SESSION['user_theme_preference'] ?? 'light');

    return in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
}

function currentUserCurrency(): string
{
    $currency = strtoupper((string) ($_SESSION['user_currency'] ?? 'USD'));
    $currencies = availableCurrencies();

    return array_key_exists($currency, $currencies) ? $currency : 'USD';
}

function appBasePath(): string
{
    static $basePath;

    if ($basePath !== null) {
        return $basePath;
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(__DIR__ . '/..') ?: '';

    if ($documentRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $documentRoot)) {
        $basePath = str_replace('\\', '/', substr($appRoot, strlen($documentRoot))) ?: '';
    } else {
        $basePath = '';
    }

    return rtrim($basePath, '/');
}

function url(string $path = ''): string
{
    $basePath = appBasePath();
    $normalizedPath = ltrim($path, '/');

    if ($normalizedPath === '') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    return ($basePath !== '' ? $basePath : '') . '/' . $normalizedPath;
}

function currentRoute(): string
{
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $basePath = appBasePath();

    if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
        $requestPath = substr($requestPath, strlen($basePath));
    }

    return ltrim($requestPath, '/');
}

function routeIs(string $path): bool
{
    return currentRoute() === ltrim($path, '/');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function rememberCookieName(): string
{
    return 'expense_tracker_remember';
}

function setRememberCookie(string $value, int $expiresAt): void
{
    $secure = isHttpsRequest();
    setcookie(rememberCookieName(), $value, [
        'expires' => $expiresAt,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberCookie(): void
{
    setRememberCookie('', time() - 3600);
}

function clearRememberTokenForCurrentCookie(): void
{
    $cookie = (string) ($_COOKIE[rememberCookieName()] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
        clearRememberCookie();
        return;
    }

    [$selector] = explode(':', $cookie, 2);
    if ($selector !== '') {
        $statement = db()->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $statement->execute(['selector' => $selector]);
    }

    clearRememberCookie();
}

function hydrateUserSession(array $user): void
{
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = (string) $user['name'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_avatar_url'] = (string) ($user['avatar_url'] ?? '');
    $_SESSION['user_currency'] = (string) ($user['preferred_currency'] ?? 'USD');
    $_SESSION['user_theme_preference'] = (string) ($user['theme_preference'] ?? 'light');
}

function createRememberToken(int $userId, int $days = 30): void
{
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expiresAt = time() + ($days * 86400);

    $deleteExpired = db()->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id OR expires_at < NOW()');
    $deleteExpired->execute(['user_id' => $userId]);

    $insert = db()->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
         VALUES (:user_id, :selector, :token_hash, :expires_at)'
    );
    $insert->execute([
        'user_id' => $userId,
        'selector' => $selector,
        'token_hash' => $tokenHash,
        'expires_at' => date('Y-m-d H:i:s', $expiresAt),
    ]);

    setRememberCookie($selector . ':' . $validator, $expiresAt);
}

function completeUserLogin(array $user, bool $rememberMe = false): void
{
    session_regenerate_id(true);
    hydrateUserSession($user);

    if ($rememberMe) {
        createRememberToken((int) $user['id']);
        return;
    }

    clearRememberTokenForCurrentCookie();
}

function tryLoginFromRememberToken(): void
{
    if (isLoggedIn()) {
        return;
    }

    $cookie = (string) ($_COOKIE[rememberCookieName()] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if ($selector === '' || $validator === '') {
        clearRememberCookie();
        return;
    }

    $statement = db()->prepare(
        'SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at, u.id AS uid, u.name, u.email, u.avatar_url, u.preferred_currency, u.theme_preference
         FROM remember_tokens rt
         INNER JOIN users u ON u.id = rt.user_id
         WHERE rt.selector = :selector
         LIMIT 1'
    );
    $statement->execute(['selector' => $selector]);
    $record = $statement->fetch();

    if (!$record) {
        clearRememberCookie();
        return;
    }

    if (strtotime((string) $record['expires_at']) < time()) {
        $delete = db()->prepare('DELETE FROM remember_tokens WHERE id = :id');
        $delete->execute(['id' => (int) $record['id']]);
        clearRememberCookie();
        return;
    }

    if (!hash_equals((string) $record['token_hash'], hash('sha256', $validator))) {
        $deleteAll = db()->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
        $deleteAll->execute(['user_id' => (int) $record['user_id']]);
        clearRememberCookie();
        return;
    }

    session_regenerate_id(true);
    hydrateUserSession([
        'id' => (int) $record['uid'],
        'name' => (string) $record['name'],
        'email' => (string) $record['email'],
        'avatar_url' => (string) ($record['avatar_url'] ?? ''),
        'preferred_currency' => (string) ($record['preferred_currency'] ?? 'USD'),
        'theme_preference' => (string) ($record['theme_preference'] ?? 'light'),
    ]);

    createRememberToken((int) $record['user_id']);

    $deleteOld = db()->prepare('DELETE FROM remember_tokens WHERE id = :id');
    $deleteOld->execute(['id' => (int) $record['id']]);
}

function currentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function currentUserExists(): bool
{
    $userId = currentUserId();

    if ($userId <= 0) {
        return false;
    }

    $statement = db()->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);

    return (int) $statement->fetchColumn() > 0;
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        redirect('');
    }
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }

    if (!currentUserExists()) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
        session_start();
        setFlash('error', 'Your session expired after a database reset. Please log in again.');
        redirect('auth/login.php');
    }
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function formatCurrency(float $amount): string
{
    $currency = currentUserCurrency();
    $symbols = [
        'USD' => '$',
        'SAR' => 'SAR ',
        'PHP' => 'PHP ',
        'EUR' => 'EUR ',
        'GBP' => 'GBP ',
    ];

    return ($symbols[$currency] ?? ($currency . ' ')) . number_format($amount, 2);
}

function formatDate(string $date): string
{
    $timestamp = strtotime($date);

    return $timestamp ? date('M d, Y', $timestamp) : $date;
}

function validateEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateAmount($amount): bool
{
    return is_numeric($amount) && (float) $amount >= 0;
}

function validateDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);

    return $parsed instanceof DateTime && $parsed->format('Y-m-d') === $date;
}

function normalizeText(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function getUserCategories(int $userId): array
{
    $statement = db()->prepare(
        'SELECT id, name, is_default
         FROM categories
         WHERE user_id = :user_id OR is_default = 1
         ORDER BY is_default DESC, name ASC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function getCurrentUser(int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT id, name, email, avatar_url, preferred_currency, theme_preference, balance, notify_reminders, notify_updates
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function availableCurrencies(): array
{
    return [
        'USD' => 'US Dollar (USD)',
        'SAR' => 'Saudi Riyal (SAR)',
        'PHP' => 'Philippine Peso (PHP)',
        'EUR' => 'Euro (EUR)',
        'GBP' => 'British Pound (GBP)',
    ];
}

function userOwnsCategory(int $userId, int $categoryId): bool
{
    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM categories
         WHERE id = :id AND (user_id = :user_id OR is_default = 1)'
    );
    $statement->execute([
        'id' => $categoryId,
        'user_id' => $userId,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function syncUserBalance(int $userId): void
{
    $statement = db()->prepare(
        'UPDATE users
         SET balance = COALESCE((
             SELECT SUM(amount)
             FROM income
             WHERE user_id = :income_user_id
         ), 0) - COALESCE((
             SELECT SUM(amount)
             FROM expenses
             WHERE user_id = :balance_user_id
               AND payment_status = "paid"
         ), 0),
         updated_at = NOW()
         WHERE id = :target_user_id'
    );
    $statement->execute([
        'income_user_id' => $userId,
        'balance_user_id' => $userId,
        'target_user_id' => $userId,
    ]);
}

function jsonResponse(bool $success, string $message, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_THROW_ON_ERROR);
    exit;
}

function isHttpsRequest(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
        return true;
    }

    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));

    return $https === 'on' || $https === '1' || $forwardedProto === 'https' || $forwardedSsl === 'on';
}

function validateCategoryNameInput(string $name): array
{
    $errors = [];

    if ($name === '') {
        $errors[] = 'Category name is required.';
    }

    if (mb_strlen($name) > 50) {
        $errors[] = 'Category name must not exceed 50 characters.';
    }

    return $errors;
}

function categoryNameExistsForUser(int $userId, string $name, int $ignoreCategoryId = 0): bool
{
    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM categories
         WHERE user_id = :user_id
           AND LOWER(name) = LOWER(:name)
           AND id <> :id'
    );
    $statement->execute([
        'user_id' => $userId,
        'name' => $name,
        'id' => $ignoreCategoryId,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function getEditableCategoryForUser(int $userId, int $categoryId): ?array
{
    $statement = db()->prepare(
        'SELECT id, user_id, name, is_default
         FROM categories
         WHERE id = :id
           AND user_id = :user_id
           AND is_default = 0
         LIMIT 1'
    );
    $statement->execute([
        'id' => $categoryId,
        'user_id' => $userId,
    ]);
    $category = $statement->fetch();

    return $category ?: null;
}

function saveCategoryForUser(int $userId, string $rawName, int $categoryId = 0): array
{
    $name = normalizeText($rawName);
    $errors = validateCategoryNameInput($name);

    if (!$errors && categoryNameExistsForUser($userId, $name, $categoryId)) {
        $errors[] = 'You already have a category with this name.';
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    if ($categoryId > 0) {
        $category = getEditableCategoryForUser($userId, $categoryId);

        if (!$category) {
            return ['success' => false, 'errors' => ['Only your custom categories can be edited.']];
        }

        $statement = db()->prepare(
            'UPDATE categories
             SET name = :name, updated_at = NOW()
             WHERE id = :id
               AND user_id = :user_id
               AND is_default = 0'
        );
        $statement->execute([
            'name' => $name,
            'id' => $categoryId,
            'user_id' => $userId,
        ]);

        return ['success' => true, 'message' => 'Category updated successfully.'];
    }

    $statement = db()->prepare(
        'INSERT INTO categories (user_id, name, is_default, created_at, updated_at)
         VALUES (:user_id, :name, 0, NOW(), NOW())'
    );
    $statement->execute([
        'user_id' => $userId,
        'name' => $name,
    ]);

    return ['success' => true, 'message' => 'Category added successfully.'];
}

function deleteCategoryForUser(int $userId, int $categoryId): array
{
    $category = getEditableCategoryForUser($userId, $categoryId);

    if (!$category) {
        return ['success' => false, 'errors' => ['Only your custom categories can be deleted.']];
    }

    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM expenses
         WHERE user_id = :user_id
           AND category_id = :category_id'
    );
    $statement->execute([
        'user_id' => $userId,
        'category_id' => $categoryId,
    ]);

    if ((int) $statement->fetchColumn() > 0) {
        return ['success' => false, 'errors' => ['This category is used by existing expenses and cannot be deleted.']];
    }

    $delete = db()->prepare(
        'DELETE FROM categories
         WHERE id = :id
           AND user_id = :user_id
           AND is_default = 0'
    );
    $delete->execute([
        'id' => $categoryId,
        'user_id' => $userId,
    ]);

    return ['success' => true, 'message' => 'Category deleted successfully.'];
}

function validateExpenseInput(int $userId, array $input): array
{
    $errors = [];
    $title = normalizeText((string) ($input['title'] ?? ''));
    $amount = $input['amount'] ?? '';
    $categoryId = (int) ($input['category_id'] ?? 0);
    $expenseDate = (string) ($input['expense_date'] ?? '');
    $paymentStatus = (string) ($input['payment_status'] ?? 'pending');

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if (!validateAmount($amount) || (float) $amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }

    if (!validateDate($expenseDate)) {
        $errors[] = 'Please provide a valid expense date.';
    }

    if (!userOwnsCategory($userId, $categoryId)) {
        $errors[] = 'Please choose a valid category.';
    }

    if (!in_array($paymentStatus, ['paid', 'pending'], true)) {
        $errors[] = 'Please choose a valid payment status.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'title' => $title,
            'amount' => (float) $amount,
            'category_id' => $categoryId,
            'expense_date' => $expenseDate,
            'payment_status' => $paymentStatus,
        ],
    ];
}

function saveExpenseForUser(int $userId, array $input, int $expenseId = 0): array
{
    $validated = validateExpenseInput($userId, $input);
    $errors = $validated['errors'];
    $data = $validated['data'];

    if ($errors) {
        return ['success' => false, 'errors' => $errors, 'data' => $data];
    }

    if ($expenseId > 0) {
        $existingExpense = getExpenseById($userId, $expenseId);

        if (!$existingExpense) {
            return ['success' => false, 'errors' => ['Expense not found.'], 'data' => $data];
        }

        $statement = db()->prepare(
            'UPDATE expenses
             SET title = :title,
                 amount = :amount,
                 category_id = :category_id,
                 expense_date = :expense_date,
                 payment_status = :payment_status,
                 updated_at = NOW()
             WHERE id = :id
               AND user_id = :user_id'
        );
        $statement->execute([
            'title' => $data['title'],
            'amount' => $data['amount'],
            'category_id' => $data['category_id'],
            'expense_date' => $data['expense_date'],
            'payment_status' => $data['payment_status'],
            'id' => $expenseId,
            'user_id' => $userId,
        ]);
        syncUserBalance($userId);

        return ['success' => true, 'message' => 'Expense updated successfully.', 'data' => $data];
    }

    $statement = db()->prepare(
        'INSERT INTO expenses (user_id, category_id, title, amount, expense_date, payment_status, created_at, updated_at)
         VALUES (:user_id, :category_id, :title, :amount, :expense_date, :payment_status, NOW(), NOW())'
    );
    $statement->execute([
        'user_id' => $userId,
        'category_id' => $data['category_id'],
        'title' => $data['title'],
        'amount' => $data['amount'],
        'expense_date' => $data['expense_date'],
        'payment_status' => $data['payment_status'],
    ]);
    syncUserBalance($userId);

    return ['success' => true, 'message' => 'Expense added successfully.', 'data' => $data];
}

function toggleExpenseStatusForUser(int $userId, int $expenseId): array
{
    $expense = getExpenseById($userId, $expenseId);

    if (!$expense) {
        return ['success' => false, 'errors' => ['Expense not found.']];
    }

    $nextStatus = ($expense['payment_status'] ?? 'pending') === 'paid' ? 'pending' : 'paid';
    $statement = db()->prepare(
        'UPDATE expenses
         SET payment_status = :payment_status, updated_at = NOW()
         WHERE id = :id
           AND user_id = :user_id'
    );
    $statement->execute([
        'payment_status' => $nextStatus,
        'id' => $expenseId,
        'user_id' => $userId,
    ]);
    syncUserBalance($userId);

    return ['success' => true, 'message' => 'Payment status updated successfully.', 'status' => $nextStatus];
}

function deleteExpenseForUser(int $userId, int $expenseId): array
{
    $expense = getExpenseById($userId, $expenseId);

    if (!$expense) {
        return ['success' => false, 'errors' => ['Expense not found.']];
    }

    $statement = db()->prepare(
        'DELETE FROM expenses
         WHERE id = :id
           AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $expenseId,
        'user_id' => $userId,
    ]);
    syncUserBalance($userId);

    return ['success' => true, 'message' => 'Expense deleted successfully.'];
}

function listExpensesForUser(int $userId, array $filters = []): array
{
    $filterCategory = (int) ($filters['category_id'] ?? 0);
    $filterFrom = (string) ($filters['from_date'] ?? '');
    $filterTo = (string) ($filters['to_date'] ?? '');

    $query = 'SELECT e.id, e.title, e.amount, e.expense_date, e.payment_status, c.name AS category_name
              FROM expenses e
              LEFT JOIN categories c ON c.id = e.category_id
              WHERE e.user_id = :user_id';
    $params = ['user_id' => $userId];

    if ($filterCategory > 0) {
        $query .= ' AND e.category_id = :category_id';
        $params['category_id'] = $filterCategory;
    }

    if ($filterFrom !== '' && validateDate($filterFrom)) {
        $query .= ' AND e.expense_date >= :from_date';
        $params['from_date'] = $filterFrom;
    }

    if ($filterTo !== '' && validateDate($filterTo)) {
        $query .= ' AND e.expense_date <= :to_date';
        $params['to_date'] = $filterTo;
    }

    $query .= ' ORDER BY e.expense_date DESC, e.id DESC';
    $statement = db()->prepare($query);
    $statement->execute($params);

    return $statement->fetchAll();
}

function getExpenseSummaryForUser(int $userId): array
{
    $statement = db()->prepare(
        'SELECT
            COALESCE(SUM(amount), 0) AS total_expenses,
            COALESCE(SUM(CASE
                WHEN YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())
                THEN amount ELSE 0 END), 0) AS monthly_expenses,
            COALESCE(SUM(CASE WHEN payment_status = "pending" THEN amount ELSE 0 END), 0) AS upcoming_deductions
         FROM expenses
         WHERE user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetch() ?: [
        'total_expenses' => 0,
        'monthly_expenses' => 0,
        'upcoming_deductions' => 0,
    ];
}

function validateIncomeInput(array $input): array
{
    $errors = [];
    $title = normalizeText((string) ($input['title'] ?? ''));
    $source = normalizeText((string) ($input['source'] ?? ''));
    $amount = $input['amount'] ?? '';
    $incomeDate = (string) ($input['income_date'] ?? '');

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if (!validateAmount($amount) || (float) $amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }

    if (!validateDate($incomeDate)) {
        $errors[] = 'Please provide a valid income date.';
    }

    return [
        'errors' => $errors,
        'data' => [
            'title' => $title,
            'source' => $source,
            'amount' => (float) $amount,
            'income_date' => $incomeDate,
        ],
    ];
}

function saveIncomeForUser(int $userId, array $input, int $incomeId = 0): array
{
    $validated = validateIncomeInput($input);
    $errors = $validated['errors'];
    $data = $validated['data'];

    if ($errors) {
        return ['success' => false, 'errors' => $errors, 'data' => $data];
    }

    if ($incomeId > 0) {
        $existingIncome = getIncomeById($userId, $incomeId);

        if (!$existingIncome) {
            return ['success' => false, 'errors' => ['Income entry not found.'], 'data' => $data];
        }

        $statement = db()->prepare(
            'UPDATE income
             SET title = :title,
                 source = :source,
                 amount = :amount,
                 income_date = :income_date,
                 updated_at = NOW()
             WHERE id = :id
               AND user_id = :user_id'
        );
        $statement->execute([
            'title' => $data['title'],
            'source' => $data['source'] !== '' ? $data['source'] : null,
            'amount' => $data['amount'],
            'income_date' => $data['income_date'],
            'id' => $incomeId,
            'user_id' => $userId,
        ]);
        syncUserBalance($userId);

        return ['success' => true, 'message' => 'Income updated successfully.', 'data' => $data];
    }

    $statement = db()->prepare(
        'INSERT INTO income (user_id, title, source, amount, income_date, created_at, updated_at)
         VALUES (:user_id, :title, :source, :amount, :income_date, NOW(), NOW())'
    );
    $statement->execute([
        'user_id' => $userId,
        'title' => $data['title'],
        'source' => $data['source'] !== '' ? $data['source'] : null,
        'amount' => $data['amount'],
        'income_date' => $data['income_date'],
    ]);
    syncUserBalance($userId);

    return ['success' => true, 'message' => 'Income added successfully.', 'data' => $data];
}

function deleteIncomeForUser(int $userId, int $incomeId): array
{
    $income = getIncomeById($userId, $incomeId);

    if (!$income) {
        return ['success' => false, 'errors' => ['Income entry not found.']];
    }

    $statement = db()->prepare(
        'DELETE FROM income
         WHERE id = :id
           AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $incomeId,
        'user_id' => $userId,
    ]);
    syncUserBalance($userId);

    return ['success' => true, 'message' => 'Income deleted successfully.'];
}

function listIncomeForUser(int $userId, array $filters = []): array
{
    $filterFrom = (string) ($filters['from_date'] ?? '');
    $filterTo = (string) ($filters['to_date'] ?? '');

    $query = 'SELECT id, title, source, amount, income_date
              FROM income
              WHERE user_id = :user_id';
    $params = ['user_id' => $userId];

    if ($filterFrom !== '' && validateDate($filterFrom)) {
        $query .= ' AND income_date >= :from_date';
        $params['from_date'] = $filterFrom;
    }

    if ($filterTo !== '' && validateDate($filterTo)) {
        $query .= ' AND income_date <= :to_date';
        $params['to_date'] = $filterTo;
    }

    $query .= ' ORDER BY income_date DESC, id DESC';
    $statement = db()->prepare($query);
    $statement->execute($params);

    return $statement->fetchAll();
}

function getIncomeSummaryForUser(int $userId): array
{
    $statement = db()->prepare(
        'SELECT
            COALESCE(SUM(amount), 0) AS total_income,
            COALESCE(SUM(CASE
                WHEN YEAR(income_date) = YEAR(CURDATE()) AND MONTH(income_date) = MONTH(CURDATE())
                THEN amount ELSE 0 END), 0) AS monthly_income,
            COALESCE(MAX(income_date), NULL) AS latest_income_date
         FROM income
         WHERE user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetch() ?: [
        'total_income' => 0,
        'monthly_income' => 0,
        'latest_income_date' => null,
    ];
}

function updateProfileForUser(int $userId, array $input, array $files = []): array
{
    $name = normalizeText((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $avatarUrl = trim((string) ($input['avatar_url'] ?? ''));
    $avatarMode = (string) ($input['avatar_mode'] ?? 'generated');
    $errors = [];

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }

    if (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($avatarMode === 'uploaded' && $avatarUrl !== '' && filter_var($avatarUrl, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'Profile picture must be a valid URL.';
    }

    if (!in_array($avatarMode, ['generated', 'uploaded'], true)) {
        $errors[] = 'Please choose a valid avatar option.';
    }

    if (
        isset($files['avatar_file']) &&
        is_array($files['avatar_file']) &&
        ($files['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {
        $fileError = (int) ($files['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = 'Profile image upload failed. Please try again.';
        } else {
            $tmpPath = (string) ($files['avatar_file']['tmp_name'] ?? '');
            $mimeType = mime_content_type($tmpPath) ?: '';
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowedTypes[$mimeType])) {
                $errors[] = 'Profile image must be JPG, PNG, or WEBP.';
            }

            if ((int) ($files['avatar_file']['size'] ?? 0) > 2 * 1024 * 1024) {
                $errors[] = 'Profile image must be smaller than 2MB.';
            }
        }
    }

    if (!$errors) {
        $existsStatement = db()->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
        $existsStatement->execute([
            'email' => $email,
            'id' => $userId,
        ]);

        if ((int) $existsStatement->fetchColumn() > 0) {
            $errors[] = 'Another account is already using this email address.';
        }
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    if (
        isset($files['avatar_file']) &&
        is_array($files['avatar_file']) &&
        ($files['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    ) {
        $mimeType = mime_content_type((string) $files['avatar_file']['tmp_name']) ?: '';
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $extension = $allowedTypes[$mimeType] ?? 'jpg';
        $uploadBaseDir = dirname(__DIR__) . '/uploads';
        $uploadDir = $uploadBaseDir . '/avatars';

        if (!is_dir($uploadBaseDir) && !mkdir($uploadBaseDir, 0775, true) && !is_dir($uploadBaseDir)) {
            return ['success' => false, 'errors' => ['Could not prepare the uploads folder. Please check folder permissions.']];
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return ['success' => false, 'errors' => ['Could not prepare the avatar upload folder. Please check folder permissions.']];
        }

        if (!is_writable($uploadDir)) {
            return ['success' => false, 'errors' => ['Avatar upload folder is not writable. Please update folder permissions for uploads/avatars.']];
        }

        $fileName = sprintf('user-%d-%d.%s', $userId, time(), $extension);
        $destinationPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file((string) $files['avatar_file']['tmp_name'], $destinationPath)) {
            return ['success' => false, 'errors' => ['Profile image could not be saved.']];
        }

        $avatarUrl = url('uploads/avatars/' . $fileName);
        $avatarMode = 'uploaded';
    }

    if ($avatarMode === 'generated') {
        $avatarUrl = '';
    }

    $statement = db()->prepare(
        'UPDATE users
         SET name = :name,
             email = :email,
             avatar_url = :avatar_url,
             updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'name' => $name,
        'email' => $email,
        'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
        'id' => $userId,
    ]);

    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_avatar_url'] = $avatarUrl;

    return ['success' => true, 'message' => 'Profile updated successfully'];
}

function updatePasswordForUser(int $userId, string $password, string $confirmPassword): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    $statement = db()->prepare(
        'UPDATE users
         SET password_hash = :password_hash, updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    return ['success' => true, 'message' => 'Password updated'];
}

function updatePreferencesForUser(int $userId, string $preferredCurrency, string $themePreference): array
{
    $preferredCurrency = strtoupper(trim($preferredCurrency));
    $themePreference = trim($themePreference);
    $currencies = availableCurrencies();
    $errors = [];

    if (!array_key_exists($preferredCurrency, $currencies)) {
        $errors[] = 'Please select a valid currency.';
    }

    if (!in_array($themePreference, ['light', 'dark'], true)) {
        $errors[] = 'Please select a valid theme.';
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    $statement = db()->prepare(
        'UPDATE users
         SET preferred_currency = :preferred_currency,
             theme_preference = :theme_preference,
             updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'preferred_currency' => $preferredCurrency,
        'theme_preference' => $themePreference,
        'id' => $userId,
    ]);

    $_SESSION['user_currency'] = $preferredCurrency;
    $_SESSION['user_theme_preference'] = $themePreference;

    return ['success' => true, 'message' => 'Preferences saved'];
}

function updateNotificationPreferencesForUser(int $userId, bool $notifyReminders, bool $notifyUpdates): array
{
    $statement = db()->prepare(
        'UPDATE users
         SET notify_reminders = :notify_reminders,
             notify_updates = :notify_updates,
             updated_at = NOW()
         WHERE id = :id'
    );
    $statement->execute([
        'notify_reminders' => $notifyReminders ? 1 : 0,
        'notify_updates' => $notifyUpdates ? 1 : 0,
        'id' => $userId,
    ]);

    return ['success' => true, 'message' => 'Notification preferences saved'];
}

function deleteAccountForUser(int $userId, string $confirmation, string $currentPassword): array
{
    $errors = [];

    if (strtolower(trim($confirmation)) !== 'delete') {
        $errors[] = 'Type DELETE to confirm account removal.';
    }

    if ($currentPassword === '') {
        $errors[] = 'Enter your current password to confirm deletion.';
    }

    if (!$errors) {
        $statement = db()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $passwordHash = (string) $statement->fetchColumn();

        if ($passwordHash === '' || !password_verify($currentPassword, $passwordHash)) {
            $errors[] = 'Current password is incorrect.';
        }
    }

    if ($errors) {
        return ['success' => false, 'errors' => $errors];
    }

    $statement = db()->prepare('DELETE FROM users WHERE id = :id');
    $statement->execute(['id' => $userId]);

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
    session_start();
    setFlash('success', 'Your account was deleted.');

    return ['success' => true, 'message' => 'Your account was deleted.'];
}

function getExpenseById(int $userId, int $expenseId): ?array
{
    $statement = db()->prepare(
        'SELECT *
         FROM expenses
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $expenseId,
        'user_id' => $userId,
    ]);

    $expense = $statement->fetch();

    return $expense ?: null;
}

function getIncomeById(int $userId, int $incomeId): ?array
{
    $statement = db()->prepare(
        'SELECT *
         FROM income
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        'id' => $incomeId,
        'user_id' => $userId,
    ]);

    $income = $statement->fetch();

    return $income ?: null;
}

function getDashboardSummary(int $userId): array
{
    $balanceStatement = db()->prepare('SELECT COALESCE(balance, 0) FROM users WHERE id = :user_id LIMIT 1');
    $balanceStatement->execute(['user_id' => $userId]);

    $monthlyStatement = db()->prepare(
        'SELECT COALESCE(SUM(amount), 0)
         FROM expenses
         WHERE user_id = :user_id
           AND YEAR(expense_date) = YEAR(CURDATE())
           AND MONTH(expense_date) = MONTH(CURDATE())'
    );
    $monthlyStatement->execute(['user_id' => $userId]);

    $recentStatement = db()->prepare(
        'SELECT e.id, e.title, e.amount, e.expense_date, c.name AS category_name
         FROM expenses e
         LEFT JOIN categories c ON c.id = e.category_id
         WHERE e.user_id = :user_id
         ORDER BY e.expense_date DESC, e.id DESC
         LIMIT 5'
    );
    $recentStatement->execute(['user_id' => $userId]);

    $chartStatement = db()->prepare(
        'SELECT DATE_FORMAT(expense_date, "%b") AS month_name, DATE_FORMAT(expense_date, "%Y-%m") AS month_key, SUM(amount) AS total
         FROM expenses
         WHERE user_id = :user_id
           AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY month_key, month_name
         ORDER BY month_key ASC'
    );
    $chartStatement->execute(['user_id' => $userId]);

    $categoryStatement = db()->prepare(
        'SELECT c.name AS category_name, SUM(e.amount) AS total
         FROM expenses e
         LEFT JOIN categories c ON c.id = e.category_id
         WHERE e.user_id = :user_id
         GROUP BY c.name
         ORDER BY total DESC
         LIMIT 5'
    );
    $categoryStatement->execute(['user_id' => $userId]);

    $upcomingStatement = db()->prepare(
        'SELECT COUNT(*) AS pending_count, COALESCE(SUM(amount), 0) AS pending_total
         FROM expenses
         WHERE user_id = :user_id
           AND payment_status = "pending"'
    );
    $upcomingStatement->execute(['user_id' => $userId]);
    $upcoming = $upcomingStatement->fetch() ?: ['pending_count' => 0, 'pending_total' => 0];

    return [
        'total_balance' => (float) $balanceStatement->fetchColumn(),
        'monthly_total' => (float) $monthlyStatement->fetchColumn(),
        'recent_transactions' => $recentStatement->fetchAll(),
        'chart_data' => $chartStatement->fetchAll(),
        'category_breakdown' => $categoryStatement->fetchAll(),
        'upcoming_deductions_total' => (float) $upcoming['pending_total'],
        'upcoming_deductions_count' => (int) $upcoming['pending_count'],
    ];
}
