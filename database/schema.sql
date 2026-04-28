CREATE DATABASE IF NOT EXISTS expense_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE expense_tracker;

DROP TABLE IF EXISTS income;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    avatar_url VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    preferred_currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    theme_preference VARCHAR(10) NOT NULL DEFAULT 'light',
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notify_reminders TINYINT(1) NOT NULL DEFAULT 1,
    notify_updates TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    name VARCHAR(50) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    UNIQUE KEY unique_user_category (user_id, name)
) ENGINE=InnoDB;

CREATE TABLE expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_status ENUM('paid', 'pending') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_expenses_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT,
    KEY idx_expenses_user_date (user_id, expense_date),
    KEY idx_expenses_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE income (
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
) ENGINE=InnoDB;

INSERT INTO users (id, name, email, avatar_url, password_hash, preferred_currency, theme_preference, balance, notify_reminders, notify_updates) VALUES
    (1, 'Demo User', 'demo@example.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'USD', 'light', -35.25, 1, 1);

INSERT INTO categories (id, user_id, name, is_default) VALUES
    (1, NULL, 'Food', 1),
    (2, NULL, 'Bills', 1),
    (3, NULL, 'Transport', 1),
    (4, NULL, 'Shopping', 1),
    (5, NULL, 'Health', 1),
    (6, NULL, 'Entertainment', 1),
    (7, NULL, 'Education', 1),
    (8, NULL, 'Other', 1),
    (9, 1, 'Pets', 0),
    (10, 1, 'Coffee', 0);

INSERT INTO expenses (user_id, category_id, title, amount, expense_date, payment_status) VALUES
    (1, 1, 'Lunch', 12.50, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'paid'),
    (1, 3, 'Taxi Ride', 18.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'pending'),
    (1, 9, 'Dog Food', 22.75, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'paid'),
    (1, 10, 'Coffee Beans', 15.99, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'pending');

INSERT INTO income (user_id, title, source, amount, income_date) VALUES
    (1, 'Salary', 'Employer', 2500.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
    (1, 'Freelance Payment', 'Client Project', 420.00, DATE_SUB(CURDATE(), INTERVAL 12 DAY));
