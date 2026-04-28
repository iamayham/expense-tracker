# Expense Tracker

A web-based personal finance tracker built with PHP, MySQL, PDO, and vanilla JavaScript.

## Live Demo
- [https://trackexpenses.org/](https://trackexpenses.org/)

## Features
- User registration, login, logout, and forgot-password flow
- Secure password hashing, session handling, and CSRF protection
- Dashboard with balance, monthly expense summary, charts, and recent transactions
- Expense management with add, edit, delete, category filters, date filters, and paid / not paid status
- Income management with add, edit, delete, and date filtering
- Default and custom categories
- User settings for profile, password, theme, notifications, and preferred currency
- Currency-aware formatting across the app

## Requirements
- XAMPP with Apache and MySQL running, or Docker
- PHP 8.2 recommended
- MySQL 5.7+ or MariaDB equivalent

## Quick Start (XAMPP)
1. Place the project in `htdocs/expense-tracker`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Create a database named `expense_tracker` if it does not already exist.
5. Import `database/schema.sql`.
6. Open the app at `http://localhost/expense-tracker/`.

### Database Config (`config/db.php`)
For XAMPP, you usually do not need environment variables.

Default values:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=expense_tracker
DB_USER=root
DB_PASS=
```

Also supported (optional environment variables):

```env
MYSQLHOST=localhost
MYSQLPORT=3306
MYSQLDATABASE=expense_tracker
MYSQLUSER=root
MYSQLPASSWORD=
```

## Docker
Build:

```bash
docker build -t expense-tracker .
```

Run:

```bash
docker run -p 8080:80 expense-tracker
```

You still need a reachable MySQL database and correct DB environment variables.

## Demo Account
- Email: `demo@example.com`
- Password: `password`

## Security Notes
- All database queries use prepared statements through PDO
- Passwords are hashed with `password_hash()`
- Login regenerates the session ID
- Mutating requests require a valid CSRF token
- Output is escaped in views

## Notes
- Default categories are shared, while custom categories belong to each user
- The sample data includes starter expenses, income entries, and categories for the demo account
- `config/db.php` contains lightweight compatibility checks so older local databases can pick up newer columns and tables automatically
