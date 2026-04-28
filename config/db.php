<?php
declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'expense_tracker';
$dbUser = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: '';

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Keep older local databases compatible when new columns are added.
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS income (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(150) NOT NULL,
                source VARCHAR(100) NULL,
                amount DECIMAL(10,2) NOT NULL,
                income_date DATE NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_income_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE,
                KEY idx_income_user_date (user_id, income_date)
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS remember_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                selector VARCHAR(24) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_remember_tokens_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE,
                UNIQUE KEY unique_remember_selector (selector),
                KEY idx_remember_user (user_id),
                KEY idx_remember_expires (expires_at)
            ) ENGINE=InnoDB"
        );

        $columnCheck = $pdo->query("SHOW COLUMNS FROM expenses LIKE 'payment_status'");

        if ($columnCheck !== false && $columnCheck->fetch() === false) {
            $pdo->exec(
                "ALTER TABLE expenses
                 ADD COLUMN payment_status ENUM('paid', 'unpaid') NOT NULL DEFAULT 'unpaid'
                 AFTER expense_date"
            );
        }

        $statusTypeCheck = $pdo->query("SHOW COLUMNS FROM expenses LIKE 'payment_status'");

        if ($statusTypeCheck !== false) {
            $statusColumn = $statusTypeCheck->fetch();

            if (is_array($statusColumn) && isset($statusColumn['Type']) && str_contains((string) $statusColumn['Type'], "'unpaid'")) {
                $pdo->exec("UPDATE expenses SET payment_status = 'pending' WHERE payment_status = 'unpaid'");
                $pdo->exec(
                    "ALTER TABLE expenses
                     MODIFY COLUMN payment_status ENUM('paid', 'pending') NOT NULL DEFAULT 'pending'"
                );
            }
        }

        $userSettingsColumns = [
            "SHOW COLUMNS FROM users LIKE 'avatar_url'" => "ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL AFTER email",
            "SHOW COLUMNS FROM users LIKE 'preferred_currency'" => "ALTER TABLE users ADD COLUMN preferred_currency VARCHAR(10) NOT NULL DEFAULT 'USD' AFTER password_hash",
            "SHOW COLUMNS FROM users LIKE 'theme_preference'" => "ALTER TABLE users ADD COLUMN theme_preference VARCHAR(10) NOT NULL DEFAULT 'light' AFTER preferred_currency",
            "SHOW COLUMNS FROM users LIKE 'notify_reminders'" => "ALTER TABLE users ADD COLUMN notify_reminders TINYINT(1) NOT NULL DEFAULT 1 AFTER theme_preference",
            "SHOW COLUMNS FROM users LIKE 'notify_updates'" => "ALTER TABLE users ADD COLUMN notify_updates TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_reminders",
            "SHOW COLUMNS FROM users LIKE 'balance'" => "ALTER TABLE users ADD COLUMN balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER theme_preference",
        ];

        foreach ($userSettingsColumns as $checkQuery => $alterQuery) {
            $columnCheck = $pdo->query($checkQuery);

            if ($columnCheck !== false && $columnCheck->fetch() === false) {
                $pdo->exec($alterQuery);
            }
        }

        $pdo->exec(
            "UPDATE users u
             SET balance = COALESCE((
                 SELECT SUM(i.amount)
                 FROM income i
                 WHERE i.user_id = u.id
             ), 0) - COALESCE((
                 SELECT SUM(e.amount)
                 FROM expenses e
                 WHERE e.user_id = u.id
                   AND e.payment_status = 'paid'
             ), 0)"
        );
    } catch (PDOException $exception) {
        // Ignore migration checks when the table does not exist yet during first import/setup.
    }
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Database connection failed. Please check your configuration.');
}
